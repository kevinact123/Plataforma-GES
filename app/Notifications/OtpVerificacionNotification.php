<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpVerificacionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $codigo) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de verificación - Plataforma GES')
            ->greeting('Plataforma GES')
            ->line('Código de verificación')
            ->line($this->codigo)
            ->line('Este código es válido durante 45 segundos.')
            ->line('Si usted no solicitó este código, ignore este mensaje.');
    }
}
