<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class MessagePolicy
{
    use ChecksTenantPermissions;

    /** The message log and the WhatsApp & SMS hub (customer phones and texts): sms.view. */
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sms.view');
    }

    public function view(User $user, Message $model): bool
    {
        return $this->allows($user, 'sms.view', $model);
    }

    public function configure(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    /** Compose, send, resend and preview SMS: sms.send. */
    public function send(User $user): bool
    {
        return $this->allows($user, 'sms.send');
    }
}
