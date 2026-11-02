<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SaveBranch;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/branches (settings.manage via route middleware). */
final class BranchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    use TranslatesDomainErrors;

    public function index(): Response
    {
        return Inertia::render('settings/branches', [
            'branches' => Branch::query()->withCount('users')->orderBy('name')->get()->map(static fn (Branch $b): array => [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'phone' => $b->phone,
                'address' => $b->address,
                'receipt_header' => $b->receipt_header,
                'receipt_footer' => $b->receipt_footer,
                'is_active' => $b->is_active,
                'users' => (int) $b->getAttribute('users_count'),
                'settings' => [
                    'allow_negative_stock' => (bool) $b->setting('allow_negative_stock'),
                    'batch_selection' => (string) $b->setting('batch_selection'),
                    'cash_rounding' => (int) $b->setting('cash_rounding'),
                    'auto_print' => (bool) $b->setting('auto_print'),
                    'paper' => (string) $b->setting('paper'),
                ],
            ])->all(),
        ]);
    }

    public function store(Request $request, SaveBranch $save): RedirectResponse
    {
        $this->guarded(fn (): Branch => $save->handle($this->actor($request), $this->validated($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(Request $request, int $branch, SaveBranch $save): RedirectResponse
    {
        $model = Branch::query()->findOrFail($branch);
        $this->guarded(fn (): Branch => $save->handle($this->actor($request), $this->validated($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.allow_negative_stock' => ['boolean'],
            'settings.auto_print' => ['boolean'],
            'settings.batch_selection' => [Rule::in(['auto', 'manual'])],
            'settings.paper' => [Rule::in(['80', '58', 'a4'])],
            'settings.cash_rounding' => ['integer', 'min:0'],
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
