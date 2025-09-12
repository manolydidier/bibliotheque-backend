<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
// Si tu veux envoyer en file d'attente, dé-commente la ligne suivante + implements ShouldQueue
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification // implements ShouldQueue
{
    use Queueable;

    public string $token;
    public string $email;
    public string $langue;

    public function __construct(string $token, string $email, string $langue = 'fr')
    {
        $this->token  = $token;
        $this->email  = $email;
        $this->langue = $langue;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // URL du front (SPA) – configurable
        $frontend = config('app.frontend_url', 'http://localhost:5173');

        // URL finale vers ta page reset du FRONT
        $resetUrl = rtrim($frontend, '/')
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($this->email)
            . '&lang=' . urlencode($this->langue);

        // Délai d’expiration du token (minutes)
        $passwords = config('auth.defaults.passwords');
        $expire    = (int) (config("auth.passwords.$passwords.expire", 60));

        // Envoie un template Blade custom (bleu)
        return (new MailMessage)
            ->subject($this->langue === 'en' ? 'Reset your password' : 'Réinitialisation de mot de passe')
            ->view('emails.auth.reset-password', [
                'user'     => $notifiable,
                'email'    => $this->email,
                'resetUrl' => $resetUrl,
                'expire'   => $expire,
                'langue'   => $this->langue,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
