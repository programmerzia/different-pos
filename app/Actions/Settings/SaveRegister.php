<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Branch;
use App\Models\Register;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;

/** Creates or updates a register (till) of a branch of the current tenant. */
final readonly class SaveRegister
{
    /** @param array{name?: string, code?: string, is_active?: bool} $data */
    public function handle(User $actor, Branch $branch, array $data, ?Register $register = null): Register
    {
        $context = app(TenantContext::class);

        return Transaction::run(fn (): Register => $context->run($context->tenant(), $branch, function () use ($actor, $branch, $data, $register): Register {
            $register = $register instanceof Register ? Register::query()->findOrFail($register->id) : null;
            $created = ! $register instanceof Register;
            $register ??= new Register;

            $name = trim((string) ($data['name'] ?? $register->name ?? ''));
            $code = strtoupper(trim((string) ($data['code'] ?? $register->code ?? '')));
            if ($name === '' || $code === '') {
                throw InvalidAdminData::required($name === '' ? 'Name' : 'Code');
            }
            $others = Register::query()->when(! $created, fn ($q) => $q->whereKeyNot($register->id));
            if ((clone $others)->where('name', $name)->exists()) {
                throw InvalidAdminData::duplicate('register', $name);
            }
            if ((clone $others)->where('code', $code)->exists()) {
                throw InvalidAdminData::duplicate('register code', $code);
            }

            $register->fill(['name' => $name, 'code' => $code, 'is_active' => (bool) ($data['is_active'] ?? ($created ? true : $register->is_active))]);
            if ($created) {
                $register->number_year = (int) now()->format('Y');
            }
            $register->save();

            Audit::log($created ? 'register.created' : 'register.updated', $register, ['branch' => $branch->code, 'code' => $code], $actor);

            return $register;
        }));
    }
}
