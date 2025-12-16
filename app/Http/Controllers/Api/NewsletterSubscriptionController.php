<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class NewsletterSubscriptionController extends Controller
{
    
    /**
     * Abonnement à la newsletter + mail de bienvenue
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name'  => ['nullable', 'string', 'max:255'],
        ]);

        $existing = NewsletterSubscription::where('email', $data['email'])->first();

        if ($existing) {
            if (! $existing->is_active) {
                $existing->update([
                    'is_active'     => true,
                    'subscribed_at' => now(),
                ]);

                // 👉 Si tu veux, tu peux renvoyer un mail de "réactivation"
                // $this->sendWelcomeEmail($existing);
            }

            return response()->json([
                'status'             => 'ok',
                'already_subscribed' => true,
                'message'            => 'Vous êtes déjà abonné.',
            ]);
        }

        $sub = NewsletterSubscription::create([
            'email'         => $data['email'],
            'name'          => $data['name'] ?? null,
            'is_active'     => true,
            'subscribed_at' => now(),
        ]);

        // 💌 Envoi de l'email de bienvenue directement depuis le contrôleur
        $this->sendWelcomeEmail($sub);

        return response()->json([
            'status'             => 'ok',
            'already_subscribed' => false,
            'message'            => 'Merci, vous êtes maintenant abonné.',
        ], 201);
    }

    /**
     * Notifier tous les abonnés actifs qu'un nouvel article est publié.
     * → Tu l'appelles après la création/publication d'un article.
     */
    public function notifyNewArticle(Article $article)
    {
        NewsletterSubscription::where('is_active', true)
            ->chunk(100, function ($subscriptions) use ($article) {
                foreach ($subscriptions as $sub) {
                    $this->sendNewArticleEmail($sub, $article);
                }
            });

        return response()->json([
            'status'  => 'ok',
            'message' => 'Notifications envoyées aux abonnés actifs.',
        ]);
    }

    /* =====================================================
     *  MÉTHODES PRIVÉES POUR L’ENVOI DES EMAILS
     * ===================================================== */

    /**
     * Envoie l'email de bienvenue
     */
  protected function sendWelcomeEmail(NewsletterSubscription $subscription): void
{
    Mail::send(
        'emails.newsletter.welcome',
        [
            'subscription' => $subscription,
            'lang'         => 'fr',
            'ctaUrl'       => config('app.url') . '/articles', // optionnel
            'appName'      => config('app.name'),
            'supportMail'  => 'support@example.com', // optionnel
        ],
        function ($message) use ($subscription) {
            $message->to($subscription->email, $subscription->name ?? null)
                ->subject('🎉 Bienvenue dans la newsletter de la bibliothèque');
        }
    );
}


    /**
     * Envoie l'email "nouvel article" à un abonné
     */
    protected function sendNewArticleEmail(NewsletterSubscription $subscription, Article $article): void
    {
        Mail::send(
            'emails.newsletter.new_article',
            [
                'subscription' => $subscription,
                'article'      => $article,
                'appName'      => config('app.name'),
                'supportMail'  => config('mail.from.address'),
                'lang'         => 'fr',
                'now'          => now(),
            ],
            function ($message) use ($subscription, $article) {
                $message->to($subscription->email, $subscription->name ?? null)
                    ->subject('Nouveau contenu : '.$article->title);
            }
        );

        // Version texte simple possible aussi :
        /*
        Mail::raw(
            "Bonjour {$subscription->name},\n\nUn nouvel article est disponible : {$article->title}\n\n" . url('/articles/'.$article->slug),
            function ($message) use ($subscription, $article) {
                $message->to($subscription->email)
                    ->subject('Nouveau contenu : ' . $article->title);
            }
        );
        */
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 200));

        $search   = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
        $status   = $request->input('status'); // all|active|unconfirmed|unsubscribed
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $allowedSort = ['created_at', 'email', 'confirmed_at'];
        $sortBy = $request->input('sort_by');

        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }

        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = NewsletterSubscription::query();

        // 🔍 Recherche texte (email + nom éventuel)
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', '%' . $search . '%');

                if (schema()->hasColumn('newsletter_subscriptions', 'name')) {
                    $q->orWhere('name', 'like', '%' . $search . '%');
                }
            });
        }

        // 🟢 Filtre status : à adapter selon ton schéma (confirmed_at / unsubscribed_at, etc.)
        if ($status && $status !== 'all') {
            $query->when($status === 'active', function ($q) {
                // ex: abonnés confirmés et non désinscrits
                $q->whereNotNull('confirmed_at')
                  ->whereNull('unsubscribed_at');
            });

            $query->when($status === 'unconfirmed', function ($q) {
                $q->whereNull('confirmed_at')
                  ->whereNull('unsubscribed_at');
            });

            $query->when($status === 'unsubscribed', function ($q) {
                $q->whereNotNull('unsubscribed_at');
            });
        }

        // 📅 Filtre par plage de dates (sur created_at)
        $query->when($dateFrom, function ($q) use ($dateFrom) {
            try {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $q->where('created_at', '>=', $from);
            } catch (\Throwable $e) {
                // ignore si date invalide
            }
        });

        $query->when($dateTo, function ($q) use ($dateTo) {
            try {
                $to = Carbon::parse($dateTo)->endOfDay();
                $q->where('created_at', '<=', $to);
            } catch (\Throwable $e) {
                // ignore si date invalide
            }
        });

        // 🧮 Tri
        $query->orderBy($sortBy, $sortDirection);

        // 📦 Pagination standard Laravel -> ton normalizeList côté front s’adapte déjà
        $subs = $query->paginate($perPage);

        return response()->json($subs);
    }

}
