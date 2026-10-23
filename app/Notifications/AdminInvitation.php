<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvitation extends Notification
{
    public function __construct(public Tenant $tenant, public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aktivasi akun administrator '.$this->tenant->displayName())
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Akun administrator untuk '.$this->tenant->name.' di '.config('app.name').' telah dibuat.')
            ->line('Silakan buat password Anda melalui tautan berikut. Tautan berlaku 48 jam dan hanya dapat digunakan sekali.')
            ->action('Aktivasi akun', route('activation.show', $this->token))
            ->line('Abaikan email ini bila Anda tidak merasa mendaftar.');
    }
}
