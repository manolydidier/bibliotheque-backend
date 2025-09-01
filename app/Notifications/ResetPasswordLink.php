<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // optionnel: queue des emails
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

class ResetPasswordLink extends Notification /* implements ShouldQueue */
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $email,
        public string $langue = 'fr'
    ) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $minutes = (int) Config::get('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);
        $front   = rtrim(Config::get('app.frontend_url', 'http://localhost:5173'), '/');

        $url = $front.'/reset-password?token='.$this->token.'&email='.urlencode($this->email);

        $subject = $this->langue === 'fr'
            ? 'Réinitialisation de votre mot de passe'
            : 'Reset your password';

        $line1 = $this->langue === 'fr'
            ? 'Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.'
            : 'You are receiving this email because we received a password reset request for your account.';

        $action = $this->langue === 'fr' ? 'Réinitialiser le mot de passe' : 'Reset Password';

        $expire = $this->langue === 'fr'
            ? "Ce lien expirera dans $minutes minutes."
            : "This link will expire in $minutes minutes.";

        $ignore = $this->langue === 'fr'
            ? "Si vous n'avez pas demandé de réinitialisation, aucune autre action n'est requise."
            : 'If you did not request a password reset, no further action is required.';

        return (new MailMessage)
            ->subject($subject)
            ->line($line1)
            ->action($action, $url)
            ->line($expire)
            ->line($ignore);
    }
}
