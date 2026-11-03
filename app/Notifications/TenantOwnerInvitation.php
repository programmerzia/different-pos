<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The Owner's first sign-in: temporary password (must be changed at first login) and the sign-in link. Not queued: the admin sees a mail failure immediately. */
final class TenantOwnerInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant, private readonly string $temporaryPassword) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof User ? (string) $notifiable->email : '';

        return (new MailMessage)
            ->subject(__('Your :business account', ['business' => $this->tenant->name]))
            ->greeting(__('Welcome to :business', ['business' => $this->tenant->name]))
            ->line(__('An Owner account has been created for you.'))
            ->line(__('Email: :email', ['email' => $email]))
            ->line(__('Temporary password: :password', ['password' => $this->temporaryPassword]))
            ->line(__('You will be asked to choose a new password the first time you sign in.'))
            ->action(__('Sign in'), route('login'));
    }
}
