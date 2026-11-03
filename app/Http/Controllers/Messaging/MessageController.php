<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\ResendMessage;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendAdHocSmsRequest;
use App\Models\CustomerGroup;
use App\Models\Message;
use App\Models\User;
use App\Sms\SendAdHocSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Http\TableQuery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /app/messages: the WhatsApp & SMS hub (baseline mockup screen 10): composer, templates and the SMS log.
 * The log needs customers.view; composing and sending need settings.manage (MessagePolicy::send).
 */
final class MessageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TenantContext $context, SendAdHocSms $sender): Response
    {
        $this->authorize('viewAny', Message::class);

        $table = TableQuery::fromRequest($request, ['created_at', 'status', 'event'], ['status', 'event'], '-created_at');
        $query = Message::query()->with('customer:id,name');

        $status = $table->filters['status'] ?? null;
        if ($status !== null && MessageStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
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

        return Inertia::render('messages/index', [
            'rows' => collect($page->items())->map(static fn (Message $m): array => [
                'id' => $m->id,
                'created_at' => $m->created_at?->toIso8601String(),
                'customer' => $m->customer?->name,
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
            'composer' => fn (): ?array => $canSend ? $this->composer($context, $sender) : null,
        ]);
    }

    /** POST /app/messages/send: queues the composed SMS for every reachable customer in the audience. */
    public function send(SendAdHocSmsRequest $request, SendAdHocSms $action): RedirectResponse
    {
        $this->authorize('send', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var array{target: string, customer?: string|null, group_id?: int|null, body: string} $data */
        $data = $request->validated();
        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;

        try {
            $result = $action->handle($user, ['target' => $data['target'], 'customer' => $data['customer'] ?? null, 'group_id' => $groupId], $data['body']);
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        Inertia::flash('toast', $result['queued'] > 0
            ? ['type' => 'success', 'message' => __('sms.ad_hoc_queued', ['count' => $result['queued']])]
            : ['type' => 'error', 'message' => __('sms.ad_hoc_none')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function composer(TenantContext $context, SendAdHocSms $sender): array
    {
        $settings = SmsSettings::fromTenant($context->tenant());
        $byGroup = $sender->recipients(['target' => 'all'])->toBase()
            ->selectRaw('customer_group_id, count(*) as aggregate')->groupBy('customer_group_id')
            ->pluck('aggregate', 'customer_group_id');

        $templates = [];
        foreach (Templates::keys() as $key) {
            $templates[] = ['key' => $key, 'body' => $settings->body($key), 'enabled' => $settings->templates[$key]['enabled']];
        }

        return [
            'configured' => $settings->driver !== 'null',
            'driver' => $settings->driver,
            'cost_per_segment' => $settings->costPerSegment,
            'shop' => $context->tenant()->name,
            'max_body' => SendAdHocSms::MAX_BODY,
            'variables' => SendAdHocSms::VARIABLES,
            'templates' => $templates,
            'counts' => [
                'all' => (int) $byGroup->sum(),
                'due' => $sender->recipients(['target' => 'due'])->count(),
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
