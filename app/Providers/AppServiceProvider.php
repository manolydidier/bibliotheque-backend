<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Article;
use App\Observers\ArticleObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Article::observe(ArticleObserver::class);

        // URL du bouton dans l'email (pointe vers ton front)
        // Store the callback in a variable so it can be reused
        $createUrlCallback = function ($notifiable, string $token) {
            $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', env('APP_URL'))), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());
            return "{$frontend}/reset-password?token={$token}&email={$email}";
        };
        ResetPassword::createUrlUsing($createUrlCallback);

        // (Optionnel) Personnaliser le contenu de l'email
        ResetPassword::toMailUsing(function ($notifiable, string $token) use ($createUrlCallback) {
            $url = $createUrlCallback
                ? call_user_func($createUrlCallback, $notifiable, $token)
                : url(config('app.frontend_url')."/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset()));

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject(__('Réinitialisation de votre mot de passe'))
                ->greeting(__('Bonjour'))
                ->line(__('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.'))
                ->action(__('Réinitialiser le mot de passe'), $url)
                ->line(__('Ce lien expirera dans :count minutes.', ['count' => config('auth.passwords.users.expire')]))
                ->line(__('Si vous n’êtes pas à l’origine de cette demande, aucune action n’est requise.'));
        });

        // ---- Ajout : RateLimiter nommé pour les actions articles ----
        RateLimiter::for('article-actions', function (Request $request) {
            // Identifie par user id sinon IP
            return [
                Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip()),
            ];
        });
        // -------------------------------------------------------------
    }
}
