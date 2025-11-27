<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
}
