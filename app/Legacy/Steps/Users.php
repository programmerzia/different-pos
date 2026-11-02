<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;
use App\Legacy\Transform\LegacyDate;
use App\Legacy\Transform\Phone;
use App\Legacy\Transform\Text;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Step 3: store_admin -> users + branch_user + role. The MD5 hash is kept only in legacy_password_md5;
 * the real password is random and unusable, and must_change_password forces a new one on first login.
 */
final readonly class Users implements Step
{
    public const string TABLE = 'store_admin';

    public function __construct(private PermissionRegistrar $permissions) {}

    public function name(): string
    {
        return 'Users';
    }

    public function run(ImportContext $context): void
    {
        $tenantId = $context->tenantId();
        $this->permissions->setPermissionsTeamId($tenantId);
        /** @var array<int, string> $roles */
        $roles = (array) config('legacy.admin_groups', []);

        $context->select(self::TABLE, ['id', 'id_admin_group', 'store_id', 'status', 'passwd', 'last_login_time'], ['username', 'mobile', 'email'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function ($rows) use ($context, $tenantId, $roles): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    if ($context->ids->has(self::TABLE, $id)) {
                        $context->skipped();

                        continue;
                    }

                    $username = $this->uniqueUsername($tenantId, Text::limit($context->text($row->username, self::TABLE, $id, 'username'), 40) ?? 'user'.$id, $context, $id);
                    $email = Text::email($context->text($row->email, self::TABLE, $id, 'email'));
                    if ($email !== null && User::query()->where('email', $email)->exists()) {
                        $context->report->add('user_email_taken', self::TABLE, $id, 'Email already used by another account; left empty');
                        $email = null;
                    }
                    $phone = Phone::toE164(Text::orNull($context->text($row->mobile, self::TABLE, $id, 'mobile')));
                    if ($phone !== null && User::query()->where('tenant_id', $tenantId)->where('phone', $phone)->exists()) {
                        $phone = null;
                    }
                    $md5 = is_string($row->passwd) && preg_match('/^[0-9a-f]{32}$/i', $row->passwd) === 1 ? strtolower($row->passwd) : null;
                    if ($md5 === null) {
                        $context->report->add('user_password_unusable', self::TABLE, $id, 'Legacy password is not an MD5 hash; the user must reset it');
                    }

                    $user = new User;
                    $user->forceFill([
                        'tenant_id' => $tenantId,
                        'name' => $username,
                        'username' => $username,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => Str::random(64),
                        'legacy_password_md5' => $md5,
                        'must_change_password' => true,
                        'is_active' => $row->status === 'ACTIVE',
                        'last_login_at' => LegacyDate::utc($row->last_login_time),
                        'legacy_id' => $id,
                    ])->save();

                    $branchId = $context->branchId((int) $row->store_id);
                    DB::table('branch_user')->insertOrIgnore(['branch_id' => $branchId, 'user_id' => $user->id, 'is_default' => true]);

                    $role = $roles[(int) $row->id_admin_group] ?? SystemRoles::CASHIER;
                    $user->assignRole($role);

                    $context->ids->put(self::TABLE, (int) $row->store_id, $id, $user->id);
                    $context->created();
                }
            });

        $this->fallbackUser($context, $tenantId);
    }

    /** History rows whose legacy user is gone are attributed to the first imported user (or a disabled stand-in). */
    private function fallbackUser(ImportContext $context, int $tenantId): void
    {
        $id = User::query()->where('tenant_id', $tenantId)->whereNotNull('legacy_id')->orderBy('id')->value('id')
            ?? User::query()->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        if (! is_int($id)) {
            $user = new User;
            $user->forceFill([
                'tenant_id' => $tenantId,
                'name' => 'Legacy import',
                'username' => 'legacy-import',
                'password' => Str::random(64),
                'must_change_password' => true,
                'is_active' => false,
            ])->save();
            $id = $user->id;
            $context->report->add('fallback_user', self::TABLE, null, 'No legacy user found; history is attributed to the disabled user [legacy-import]');
        }

        $context->setLookup('fallback_user', $id);
    }

    private function uniqueUsername(int $tenantId, string $username, ImportContext $context, int $legacyId): string
    {
        $candidate = $username;
        $suffix = 2;
        while (User::query()->where('tenant_id', $tenantId)->where('username', $candidate)->exists()) {
            $candidate = $username.'-'.$suffix++;
        }
        if ($candidate !== $username) {
            $context->report->add('username_renamed', self::TABLE, $legacyId, "Username [{$username}] already taken; imported as [{$candidate}]");
        }

        return $candidate;
    }
}
