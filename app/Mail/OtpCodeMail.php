<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public int $ttl;
    public string $lang;

    public function __construct(string $code, int $ttlSeconds = 120, string $lang = 'fr')
    {
        $this->code = $code;
        $this->ttl  = $ttlSeconds;
        $this->lang = $lang;
    }

    public function build()
    {
        $subject = $this->lang === 'fr'
            ? 'Votre code de vérification'
            : 'Your verification code';

        return $this->subject($subject)
            ->markdown('emails.otp.code');
    }
}
