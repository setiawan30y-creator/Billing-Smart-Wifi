<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset password '.config('app.name'))
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun Anda.')
            ->action('Atur ulang password', $url)
            ->line("Tautan berlaku {$minutes} menit dan hanya dapat digunakan sekali.")
            ->line('Abaikan email ini bila Anda tidak meminta reset password — password Anda tidak akan berubah.');
    }
}
