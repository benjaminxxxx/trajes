<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al dueño de la plataforma: alguien se registró por primera vez.
 * Se envía a los correos de config('accounts.signup_notification_emails').
 */
class NewAccountRegistered extends Notification
{
    public function __construct(public User $user) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $method = $this->user->google_id ? 'Cuenta de Google' : 'Correo y contraseña';

        return (new MailMessage)
            ->subject('Nueva cuenta registrada: '.$this->user->name)
            ->greeting('Nueva cuenta registrada')
            ->line('Una persona acaba de crear su cuenta en '.config('app.name').'.')
            ->line("**Nombre:** {$this->user->name}  \n**Correo:** {$this->user->email}  \n**Registro con:** {$method}  \n**Fecha:** ".$this->user->created_at?->format('d/m/Y H:i'))
            ->action('Ver en el panel', route('platform.accounts.index'))
            ->line('Recibes este aviso porque tu correo está configurado como contacto de la plataforma.');
    }
}
