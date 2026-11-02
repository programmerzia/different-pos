<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\ResendMessage;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Support\Http\TableQuery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /app/messages: the SMS log. */
final class MessageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
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
            'canResend' => $user instanceof User && $user->can('send', Message::class),
        ]);
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
