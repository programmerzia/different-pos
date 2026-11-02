<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SaveRegister;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Register;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/registers: the tills of every branch of the business (settings.manage via route middleware). */
final class RegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    use TranslatesDomainErrors;

    public function index(): Response
    {
        return Inertia::render('settings/registers', [
            'branches' => Branch::query()->orderBy('name')->get(['id', 'code', 'name'])->map(static fn (Branch $b): array => [
                'id' => $b->id, 'code' => $b->code, 'name' => $b->name,
            ])->all(),
            'registers' => Register::query()->withoutBranchScope()->orderBy('branch_id')->orderBy('name')->get()
                ->map(static fn (Register $r): array => [
                    'id' => $r->id,
                    'branch_id' => $r->branch_id,
                    'name' => $r->name,
                    'code' => $r->code,
                    'is_active' => $r->is_active,
                ])->all(),
        ]);
    }

    public function store(Request $request, SaveRegister $save): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['branch_id' => ['required', 'integer']]);
        $branch = Branch::query()->findOrFail((int) $data['branch_id']);
        $this->guarded(fn (): Register => $save->handle($this->actor($request), $branch, $data));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(Request $request, int $register, SaveRegister $save): RedirectResponse
    {
        $model = Register::query()->withoutBranchScope()->findOrFail($register);
        $branch = Branch::query()->findOrFail($model->branch_id);
        $data = $request->validate($this->rules());
        $this->guarded(fn (): Register => $save->handle($this->actor($request), $branch, $data, $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'code' => ['required', 'string', 'max:6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
