<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\ResendMessage;
use App\Actions\Messaging\SendDirectMessage;
use App\Actions\Messaging\SendSmsBroadcast;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendDirectMessageRequest;
use App\Http\Requests\Messaging\SendSmsBroadcastRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Message;
use App\Models\User;
use App\Sms\BroadcastAudience;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Http\TableQuery;
use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /app/messages: the WhatsApp & SMS hub (baseline mockup screen 10): composer (both channels), templates and the
 * message log.
 * The log needs sms.view; composing and sending need sms.send (MessagePolicy).
 */
final class MessageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TenantContext $context, BroadcastAudience $audience): Response
    {
        $this->authorize('viewAny', Message::class);

        $table = TableQuery::fromRequest($request, ['created_at', 'status', 'event'], ['status', 'event', 'channel'], '-created_at');
        $query = Message::query()->with('customer:id,name');

        $status = $table->filters['status'] ?? null;
        if ($status !== null && MessageStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
        $channel = $table->filters['channel'] ?? null;
        if ($channel !== null && MessageChannel::tryFrom($channel) !== null) {
            $query->where('channel', $channel);
        }
        if (isset($table->filters['event'])) {
            $query->where('event', $table->filters['event']);
        }
        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('phone', 'like', $like)->orWhere('body', 'like', $like));
        }

        $page = $table->paginate($query->orderByDesc('id'));
        $user = $request->user();
        $canSend = $user instanceof User && $user->can('send', Message::class);

        $timezone = BusinessDay::timezone($context->tenant());

        return Inertia::render('messages/index', [
            'rows' => collect($page->items())->map(static fn (Message $m): array => [
                'id' => $m->id,
                'created_at' => BusinessDay::localIso($m->created_at, $timezone),
                'customer' => $m->customer?->name,
                'channel' => $m->channel->value,
                'phone' => $m->phone,
                'event' => $m->event,
                'body' => $m->body,
                'status' => $m->status->value,
                'error' => $m->error,
                'driver' => $m->driver,
            ])->values()->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'canResend' => $canSend,
            'costPerSegment' => fn (): int => SmsSettings::fromTenant($context->tenant())->costPerSegment,
            'whatsapp' => fn (): array => $this->whatsApp($context),
            'composer' => fn (): ?array => $canSend ? $this->composer($context, $audience) : null,
        ]);
    }

    /**
     * POST /app/messages/send: records the broadcast and queues its fan-out (SendSmsBroadcast). A repeated
     * idempotency key (double submit, retry after a timeout) sends nothing again and says so.
     */
    public function send(SendSmsBroadcastRequest $request, SendSmsBroadcast $action): RedirectResponse
    {
        $this->authorize('send', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var array{channel?: string|null, target: string, customer?: string|null, group_id?: int|null, body: string, template?: string|null, idempotency_key: string} $data */
        $data = $request->validated();
        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
        $channel = MessageChannel::tryFrom($data['channel'] ?? '') ?? MessageChannel::Sms;

        try {
            $result = $action->handle(
                $user,
                ['target' => $data['target'], 'customer' => $data['customer'] ?? null, 'group_id' => $groupId],
                $data['body'],
                $data['idempotency_key'],
                $data['template'] ?? null,
                $channel,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        $count = $result['broadcast']->recipient_count;
        Inertia::flash('toast', $result['created']
            ? ['type' => 'success', 'message' => __('sms.ad_hoc_queued', ['count' => $count])]
            : ['type' => 'warning', 'message' => __('sms.broadcast.duplicate', ['count' => $count])]);

        return back();
    }

    /**
     * POST /app/messages/direct (JSON): a message to an explicit list of customers (SendDirectMessage): the dues
     * list's WhatsApp reminder (single or selected debtors), the receipt's WhatsApp thanks, the SMS due reminder.
     * Returns the counts and, for WhatsApp click-to-chat, the wa.me link(s) the page opens in a new tab.
     */
    public function direct(SendDirectMessageRequest $request, SendDirectMessage $action): JsonResponse
    {
        $this->authorize('send', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var array{channel: string, event: string, customers: list<string>, body?: string|null, values?: array<string, string>|null} $data */
        $data = $request->validated();
        $customers = Customer::query()->whereIn('uuid', $data['customers'])->get();

        try {
            $result = $action->handle(
                $user,
                $customers,
                MessageChannel::from($data['channel']),
                $data['event'],
                $data['body'] ?? null,
                $data['values'] ?? [],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /** @return array<string, mixed> the truthful WhatsApp status pill */
    private function whatsApp(TenantContext $context): array
    {
        $settings = WhatsAppSettings::fromTenant($context->tenant());

        return ['driver' => $settings->driver, 'status' => $settings->status(), 'configured' => $settings->configured(), 'bulk' => $settings->bulk()];
    }

    /** @return array<string, mixed> */
    private function composer(TenantContext $context, BroadcastAudience $audience): array
    {
        $settings = SmsSettings::fromTenant($context->tenant());
        // Only customers with a valid mobile number count (the same rule as the fan-out), so the cost estimate holds.
        $byGroup = $audience->reachable()->toBase()
            ->selectRaw('customer_group_id, count(*) as aggregate')->groupBy('customer_group_id')
            ->pluck('aggregate', 'customer_group_id');

        $templates = [];
        foreach (Templates::keys() as $key) {
            $templates[] = ['key' => $key, 'broadcastable' => Templates::unfilled($settings->body($key), BroadcastAudience::FILLED) === [], 'body' => $settings->body($key), 'enabled' => $settings->templates[$key]['enabled']];
        }

        return [
            'configured' => $settings->driver !== 'null',
            'sending_enabled' => $settings->sendingEnabled,
            'driver' => $settings->driver,
            'whatsapp' => $this->whatsApp($context),
            'cost_per_segment' => $settings->costPerSegment,
            'shop' => $context->tenant()->name,
            'max_body' => BroadcastAudience::MAX_BODY,
            'variables' => BroadcastAudience::VARIABLES,
            'templates' => $templates,
            'counts' => [
                'all' => (int) $byGroup->sum(),
                'due' => $audience->recipients(['target' => 'due'])->count(),
            ],
            'groups' => array_values(CustomerGroup::query()->orderBy('name')->get(['id', 'name'])
                ->map(static fn (CustomerGroup $g): array => ['id' => $g->id, 'name' => $g->name, 'count' => (int) ($byGroup[$g->id] ?? 0)])
                ->all()),
        ];
    }

    public function resend(Request $request, Message $message, ResendMessage $action): RedirectResponse
    {
        $this->authorize('send', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $action->handle($user, $message);
        } catch (DomainException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('sms.requeued')]);

        return back();
    }
}
