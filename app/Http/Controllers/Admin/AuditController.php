<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditPresenter;
use App\Support\Http\TableQuery;
use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** /app/settings/audit: the business's activity log, newest first (audit.view via route middleware). */
final class AuditController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:audit.view')];
    }

    public function index(Request $request): Response
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $table = TableQuery::fromRequest($request, ['created_at'], ['from', 'to', 'user', 'subject', 'event'], '-created_at');

        $timezone = BusinessDay::timezone(app(TenantContext::class)->tenant());

        $query = Activity::query()->where('tenant_id', $tenantId)->with('causer')
            ->when($table->search !== '', function (Builder $q) use ($table): void {
                $like = '%'.addcslashes($table->search, '\\%_').'%';
                $q->where(fn (Builder $w) => $w->where('description', 'like', $like)->orWhere('event', 'like', $like));
            })
            ->when(isset($table->filters['user']), fn (Builder $q) => $q->where('causer_type', User::class)->where('causer_id', (int) $table->filters['user']))
            ->when(isset($table->filters['subject']), fn (Builder $q) => $q->where('subject_type', $table->filters['subject']))
            ->when(isset($table->filters['event']), fn (Builder $q) => $q->where('event', $table->filters['event']));
        // From/to are local business days, like every other list (timestamps are stored in UTC).
        BusinessDay::whereLocalDates($query, 'created_at', $table->filters['from'] ?? null, $table->filters['to'] ?? null, $timezone);

        $page = $table->applySort($query)->orderByDesc('id')->paginate($table->perPage, ['*'], 'page', $table->page);

        return Inertia::render('settings/audit', [
            'entries' => (new AuditPresenter($tenantId, $timezone))->rows($page->items()),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'users' => User::query()->inCurrentTenant()->orderBy('name')->get(['id', 'name'])->map(static fn (User $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
            'events' => Activity::query()->where('tenant_id', $tenantId)->whereNotNull('event')->distinct()->orderBy('event')->pluck('event')->map(static fn (mixed $e): string => (string) $e)->values()->all(),
            'subjects' => Activity::query()->where('tenant_id', $tenantId)->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')
                ->map(static fn (string $type): array => ['value' => $type, 'label' => class_basename($type)])->values()->all(),
        ]);
    }
}
