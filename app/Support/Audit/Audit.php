<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * The one way to write an audit entry by hand (business events such as sale.voided, pin.reset).
 * Model create/update/delete entries come from the LogsTenantActivity concern; both end up in
 * the same pipeline, which stamps tenant_id and scrubs secrets (see self::prepare()).
 */
final class Audit
{
    /** Attributes that must never appear in an audit row, at any depth. */
    public const array FORBIDDEN = [
        'password', 'pin', 'pin_hash', 'remember_token', 'legacy_password_md5', 'member_pin_box',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'pin_box_secret_key', 'api_token',
    ];

    /** @param  array<string, mixed>  $properties */
    public static function log(string $event, Model $subject, array $properties = [], ?User $causer = null): void
    {
        $logger = activity()->performedOn($subject)->event($event);

        $causer ??= Auth::user();
        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        }

        $logger->withProperties(self::scrub($properties))->log($event);
    }

    /** Stamps tenant_id (subject's tenant, else the context's; NULL for platform events) and scrubs secrets. */
    public static function prepare(ActivityContract $activity): void
    {
        if (! $activity instanceof Model) {
            return;
        }

        $subject = $activity->subject()->getResults();
        $context = app(TenantContext::class);
        $tenantId = match (true) {
            $subject instanceof Tenant => $subject->id,
            $subject instanceof Model && $subject->getAttribute('tenant_id') !== null => $subject->getAttribute('tenant_id'),
            $context->hasTenant() => $context->tenantId(),
            default => null,
        };
        $activity->setAttribute('tenant_id', $tenantId);

        foreach (['properties', 'attribute_changes'] as $column) {
            $value = $activity->getAttribute($column);
            if ($value !== null) {
                $activity->setAttribute($column, self::scrub($value instanceof Collection ? $value->all() : (array) $value));
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function scrub(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN, true)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::scrub($value) : $value;
        }

        return $clean;
    }
}
