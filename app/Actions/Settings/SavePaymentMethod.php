<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\PaymentMethodType;
use App\Exceptions\Domain\InvalidAdminData;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/**
 * Creates or updates a payment method. Store credit and exchange credit are system tenders: they
 * cannot be created here, retyped or switched off. The last active cash method must stay.
 */
final readonly class SavePaymentMethod
{
    private const array SYSTEM_TYPES = [PaymentMethodType::CustomerCredit, PaymentMethodType::ExchangeCredit];

    /** @param array{name?: string, type?: string, requires_reference?: bool, sort_order?: int, is_active?: bool} $data */
    public function handle(User $actor, array $data, ?PaymentMethod $method = null): PaymentMethod
    {
        return Transaction::run(function () use ($actor, $data, $method): PaymentMethod {
            $method = $method instanceof PaymentMethod ? PaymentMethod::query()->findOrFail($method->id) : null;
            $created = ! $method instanceof PaymentMethod;
            $method ??= new PaymentMethod;

            $name = trim((string) ($data['name'] ?? $method->name ?? ''));
            if ($name === '') {
                throw InvalidAdminData::required('Name');
            }
            if (PaymentMethod::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when(! $created, fn ($q) => $q->whereKeyNot($method->id))->exists()) {
                throw InvalidAdminData::duplicate('payment method', $name);
            }

            $type = isset($data['type']) ? PaymentMethodType::tryFrom($data['type']) : ($created ? PaymentMethodType::Cash : $method->type);
            if (! $type instanceof PaymentMethodType) {
                throw InvalidAdminData::invalid('Unknown payment method type.');
            }
            if ($created && in_array($type, self::SYSTEM_TYPES, true)) {
                throw InvalidAdminData::invalid('Store credit and exchange credit are created by the system.');
            }
            if (! $created && $type !== $method->type) {
                throw InvalidAdminData::invalid('The type of a payment method cannot be changed.');
            }

            $active = (bool) ($data['is_active'] ?? ($created ? true : $method->is_active));
            if (! $created && $method->is_active && ! $active) {
                if (in_array($type, self::SYSTEM_TYPES, true)) {
                    throw InvalidAdminData::invalid('A system tender cannot be switched off.');
                }
                if ($type === PaymentMethodType::Cash && ! PaymentMethod::query()->where('type', PaymentMethodType::Cash)->where('is_active', true)->whereKeyNot($method->id)->exists()) {
                    throw InvalidAdminData::invalid('At least one cash method must stay active.');
                }
            }

            $method->fill([
                'name' => $name,
                'type' => $type,
                'requires_reference' => (bool) ($data['requires_reference'] ?? $method->requires_reference ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? $method->sort_order ?? 0),
                'is_active' => $active,
            ]);
            $method->save();

            Audit::log($created ? 'payment_method.created' : 'payment_method.updated', $method, ['name' => $name, 'type' => $type->value], $actor);

            return $method;
        });
    }
}
