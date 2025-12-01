<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageSubmitted;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicContactController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $data = $request->validated();

        // 🔒 honeypot : si rempli → probable bot, on peut "faire semblant"
        if (!empty($data['company'])) {
            // Option 1 : on ignore mais on renvoie un succès générique.
            return response()->json([
                'message' => 'Votre message a bien été pris en compte.',
            ], 200);
        }

        // Adresse de réception configurable
        $toEmail = Config::get('contact.to_address');

        // Enregistrer en base
        $message = new ContactMessage();
        $message->fill($data);
        $message->ip_address   = $request->ip();
        $message->user_agent   = Str::limit((string) $request->userAgent(), 500);
        $message->sent_to_email = $toEmail;
        $message->status       = 'new';
        $message->save();

        // Envoyer le mail
        if ($toEmail) {
          Mail::to($toEmail)->send(new ContactMessageSubmitted($message));
        }

        return response()->json([
            'message' => 'Votre message a bien été envoyé.',
        ], 201);
    }
}
