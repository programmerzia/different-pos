<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Database\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Bootstraps (or re-flags) the first platform admin: a user with no tenant and is_platform_admin = 1. */
final class PlatformMakeAdmin extends Command
{
    protected $signature = 'platform:make-admin {email : Sign-in email} {--name= : Display name} {--password= : Password (generated and printed when omitted)}';

    protected $description = 'Create a platform admin (no tenant) who can open /platform';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Give a valid email address.');

            return self::FAILURE;
        }

        $existing = User::query()->withTrashed()->where('email', $email)->first();
        if ($existing instanceof User && $existing->tenant_id !== null) {
            $this->error("{$email} belongs to a business; a platform admin must be a separate account.");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: '');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(16, symbols: false);
        }
        $name = (string) ($this->option('name') ?: ($existing instanceof User ? $existing->name : 'Platform Admin'));

        $user = Transaction::run(function () use ($existing, $email, $name, $password): User {
            $user = $existing ?? new User;
            if ($user->trashed()) {
                $user->restore();
            }
            $user->fill(['name' => $name, 'email' => $email]);
            $user->forceFill(['tenant_id' => null, 'is_platform_admin' => true, 'is_active' => true, 'email_verified_at' => $user->email_verified_at ?? now()]);
            if (! $user->exists || $password !== '') {
                $user->password = $password;
            }
            $user->save();

            return $user;
        });

        $this->info(($existing instanceof User ? 'Updated' : 'Created')." platform admin {$user->email} (id {$user->id}).");
        if ($generated) {
            $this->line("Password: {$password}");
            $this->comment('Change it after the first sign-in at /settings/security.');
        }

        return self::SUCCESS;
    }
}
