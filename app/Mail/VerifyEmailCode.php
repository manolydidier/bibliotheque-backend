<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmailCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $lang;
    public string $intent;

    public function __construct(string $code, string $lang = 'fr', string $intent = 'login')
    {
        $this->code = $code;
        $this->lang = $lang;
        $this->intent = $intent;
    }

    public function build()
    {
        $subject = $this->lang === 'fr'
            ? 'Votre code de vérification'
            : 'Your verification code';

        return $this->subject($subject)
            ->view('emails.verify-email-code');
    }
}
