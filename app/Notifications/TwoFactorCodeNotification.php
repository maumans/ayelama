<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $validityMinutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de vérification')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Voici votre code de vérification pour finaliser votre connexion :')
            ->line(new \Illuminate\Support\HtmlString(
                '<div style="font-size:28px;letter-spacing:8px;font-weight:bold;text-align:center;margin:16px 0;">'
                . e($this->code) . '</div>'
            ))
            ->line("Ce code est valable {$this->validityMinutes} minute(s).")
            ->line("Ne communiquez jamais ce code, même à quelqu'un se présentant comme un membre de l'étude.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet email et changez votre mot de passe.");
    }
}
