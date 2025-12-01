<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public ContactMessage $contactMessage;

    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
    }

    public function build(): self
    {
        $m = $this->contactMessage;

        // Adresse de destination configurée dans config/contact.php
        $toAddress = config('contact.to_address');

        return $this
            // destinataire configurable
            ->to($toAddress)
            // reply-to = l’expéditeur du formulaire
            ->replyTo($m->email, $m->name ?: null)
            // sujet
            ->subject('[Contact] ' . ($m->subject ?? 'Nouveau message'))
            // vue HTML full custom (celle qu’on a écrite)
            ->view('emails.contact.submitted', [
                'm' => $m,
            ]);
    }
}
