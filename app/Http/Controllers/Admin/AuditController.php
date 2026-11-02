<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\TableQuery;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
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
        $table = TableQuery::fromRequest($request, ['created_at'], ['from', 'to', 'user', 'subject'], '-created_at');

        $from = $this->day($table->filters['from'] ?? '');
        $to = $this->day($table->filters['to'] ?? '');

        $query = Activity::query()->where('tenant_id', $tenantId)->with('causer')
            ->when($table->search !== '', function (Builder $q) use ($table): void {
                $like = '%'.addcslashes($table->search, '\\%_').'%';
                $q->where(fn (Builder $w) => $w->where('description', 'like', $like)->orWhere('event', 'like', $like));
            })
            ->when($from instanceof CarbonImmutable, fn (Builder $q) => $q->where('created_at', '>=', $from?->startOfDay()))
            ->when($to instanceof CarbonImmutable, fn (Builder $q) => $q->where('created_at', '<=', $to?->endOfDay()))
            ->when(isset($table->filters['user']), fn (Builder $q) => $q->where('causer_type', User::class)->where('causer_id', (int) $table->filters['user']))
            ->when(isset($table->filters['subject']), fn (Builder $q) => $q->where('subject_type', $table->filters['subject']));

        $page = $table->applySort($query)->orderByDesc('id')->paginate($table->perPage, ['*'], 'page', $table->page);

        return Inertia::render('settings/audit', [
            'entries' => $this->rows($page->items()),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'users' => User::query()->inCurrentTenant()->orderBy('name')->get(['id', 'name'])->map(static fn (User $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
            'subjects' => Activity::query()->where('tenant_id', $tenantId)->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')
                ->map(static fn (string $type): array => ['value' => $type, 'label' => class_basename($type)])->values()->all(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function rows(array $items): array
    {
        $rows = [];
        foreach ($items as $a) {
            if (! $a instanceof Activity) {
                continue;
            }
            $causer = $a->causer;
            $rows[] = [
                'id' => $a->id,
                'created_at' => $a->created_at?->toIso8601String(),
                'event' => $a->event,
                'description' => $a->description,
                'user' => $causer instanceof User ? $causer->name : null,
                'subject_type' => $a->subject_type !== null ? class_basename($a->subject_type) : null,
                'subject_id' => $a->subject_id,
                'properties' => collect($a->properties)->all(),
                'changes' => collect($a->attribute_changes)->all(),
            ];
        }

        return $rows;
    }

    private function day(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
