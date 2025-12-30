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
use Illuminate\Support\Facades\Cache;
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

    public function store(Request $request): JsonResponse
    {
        // ✅ 0) Rate-limit "soft" en plus du middleware (optionnel)
        // Empêche bursts très rapides par IP (sans casser UX)
        $ip = $request->ip() ?? 'unknown';
        $softKey = 'contact:soft:' . hash('sha256', $ip);
        $count = (int) Cache::get($softKey, 0);
        if ($count >= 20) { // ex: 20 tentatives / 10 min
            return response()->json(['message' => 'Trop de tentatives. Réessayez plus tard.'], 429);
        }
        Cache::put($softKey, $count + 1, now()->addMinutes(10));

        // ✅ 1) Validation
        // NOTE: 'type' laissé libre mais encadré (caractères autorisés)
        // IMPORTANT: tu ne reçois pas "consent" côté backend -> garde-le côté front uniquement
        $data = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:120'],
            'email'   => ['required', 'email:rfc,dns', 'max:190'],
            'subject' => ['required', 'string', 'min:3', 'max:140'],
            'type'    => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\pN\s\-\’\'\(\)\.\,\/]+$/u'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],

            // honeypots (robots)
            'company' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'string', 'max:80'],
        ]);

        // ✅ 2) Normalisation + anti CRLF injection (headers/mail injection)
        foreach (['email', 'subject', 'name', 'type'] as $k) {
            $data[$k] = trim(str_replace(["\r", "\n"], ' ', (string) ($data[$k] ?? '')));
        }
        $data['email']   = strtolower($data['email']);
        $data['message'] = trim((string) $data['message']);

        $ua = Str::limit((string) $request->userAgent(), 500);

        // ✅ 3) Honeypots => silent success
        if (!empty($data['company']) || !empty($data['website'])) {
            return response()->json(['message' => 'Votre message a bien été pris en compte.'], 200);
        }

        // ✅ 4) Anti-doublon (même email + même message) pendant 10 minutes
        // (évite spam + double clic)
        $dupKey = 'contact:dup:' . hash('sha256', $data['email'] . '|' . $data['message']);
        if (Cache::has($dupKey)) {
            return response()->json(['message' => 'Votre message a bien été pris en compte.'], 200);
        }
        Cache::put($dupKey, true, now()->addMinutes(10));

        // ✅ 5) Spam score basique (tu peux ajuster)
        $spamScore = 0;
        $text = mb_strtolower($data['subject'] . ' ' . $data['message']);

        // Trop de liens
        $linksCount = preg_match_all('/https?:\/\/|www\./i', $text) ?: 0;
        if ($linksCount >= 2) $spamScore += 40;

        // Répétitions de mots (spam “buy buy buy …”)
        if (preg_match('/(\b\w+\b)(?:\s+\1){6,}/iu', $text)) $spamScore += 30;

        // Message trop court
        if (mb_strlen($data['message']) < 20) $spamScore += 10;

        // Si trop suspect => silent success
        if ($spamScore >= 60) {
            return response()->json(['message' => 'Votre message a bien été pris en compte.'], 200);
        }

        // ✅ 6) Sauvegarde en base
        $toEmail = Config::get('contact.to_address');

        $message = new ContactMessage();
        $message->fill([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'subject' => $data['subject'],
            'type'    => $data['type'],
            'message' => $data['message'],
        ]);

        $message->ip_address    = $ip;
        $message->user_agent    = $ua;
        $message->sent_to_email = $toEmail;
        $message->status        = 'new';

        // Optionnel (si colonnes existent dans ta table)
        if (isset($message->spam_score)) {
            $message->spam_score = $spamScore;
        }
        if (isset($message->fingerprint)) {
            $message->fingerprint = hash('sha256', $ip . '|' . $ua . '|' . $data['email']);
        }

        $message->save();

        // ✅ 7) Envoi email (send() = fiable en local)
        if ($toEmail) {
            try {
                Mail::to($toEmail)->send(new ContactMessageSubmitted($message));
            } catch (\Throwable $e) {
                report($e);
                // Ne pas exposer l’erreur au public (mais log côté serveur)
            }
        }

        return response()->json(['message' => 'Votre message a bien été envoyé.'], 201);
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
