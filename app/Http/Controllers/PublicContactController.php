<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageSubmitted;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status   = $request->get('status', 'all');
        $search   = $request->get('q');
        $perPage  = (int) $request->get('per_page', 20);

        // sécuriser per_page
        if ($perPage < 1) {
            $perPage = 20;
        } elseif ($perPage > 100) {
            $perPage = 100;
        }

        $query = ContactMessage::query();

        // Filtre statut
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Recherche full-text simple
        if (!empty($search)) {
            $search = trim($search);

            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $messages = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        // On renvoie la pagination Laravel standard
        return response()->json($messages);
    }

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
        $message->ip_address    = $request->ip();
        $message->user_agent    = Str::limit((string) $request->userAgent(), 500);
        $message->sent_to_email = $toEmail;
        $message->status        = 'new';
        $message->save();

        // Envoyer le mail
        if ($toEmail) {
            Mail::to($toEmail)->send(new ContactMessageSubmitted($message));
        }

        return response()->json([
            'message' => 'Votre message a bien été envoyé.',
        ], 201);
    }
    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json([
            'message' => 'Message de contact supprimé avec succès.',
        ], 200);
    }
    public function update(Request $request, ContactMessage $contactMessage)
{
    $data = $request->validate([
        'status' => ['required', 'in:new,in_progress,processed'],
    ]);

    $contactMessage->status = $data['status'];
    $contactMessage->save();

    return response()->json([
        'data' => $contactMessage,
    ]);
}

}
