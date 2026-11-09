<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $roleLabel = 'account',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontend.'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset()).'&invite=1';

        return (new MailMessage)
            ->subject('You have been invited to LuxeShine Cleaning')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you on LuxeShine Cleaning as a '.$this->roleLabel.'.')
            ->line('Please set your password to activate your account and sign in.')
            ->action('Set Your Password', $url)
            ->line('This invitation link will expire in 60 minutes. You can request a new one from the login page.');
    }
}
