<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShareMethod;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ShareController extends Controller
{
    /**
     * GET /api/share/ping
     */
    public function ping()
    {
        return response()->json(['ok' => true, 'at' => now()->toIso8601String()]);
    }

    /**
     * POST /api/share/email
     * Payload: to (array|string "a;b,c"), subject, body, url?, article_id?, sender_email?, sender_name?
     */
    public function email(Request $request)
    {
        $data = $request->validate([
            'to'           => ['required'],
            'subject'      => ['required','string','max:200'],
            'body'         => ['required','string','max:10000'],
            'url'          => ['nullable','url'],
            'article_id'   => ['nullable','integer','exists:articles,id'],
            'sender_email' => ['nullable','email'],
            'sender_name'  => ['nullable','string','max:100'],
        ]);

        $article = !empty($data['article_id']) ? Article::query()->find($data['article_id']) : null;

        $recipients = is_array($data['to'])
            ? array_values(array_filter($data['to']))
            : preg_split('/[;,]/', (string) $data['to'], -1, PREG_SPLIT_NO_EMPTY);

        // Vue d'email simple
        $html = view('emails.share-article', [
            'body'    => $data['body'],
            'url'     => $data['url'] ?? $article?->getUrl(),
            'article' => $article,
        ])->render();

        Mail::html($html, function ($message) use ($recipients, $data) {
            $message->to($recipients)->subject($data['subject']);
            if (!empty($data['sender_email'])) {
                $message->replyTo($data['sender_email'], $data['sender_name'] ?? null);
            }
        });

        // Multi-tenant optionnel
        $tenantId = app()->bound('tenant') && app('tenant') ? (app('tenant')->id ?? null) : null;

        // Log du partage
        $share = ArticleShare::create([
            'tenant_id'  => $tenantId,
            'article_id' => $article?->id,
            'user_id'    => Auth::id(),
            'method'     => ShareMethod::EMAIL,
            'platform'   => 'email',
            'url'        => $data['url'] ?? null,
            'meta'       => [
                'recipients'   => $recipients,
                'subject'      => $data['subject'],
                'sender_email' => $data['sender_email'] ?? null,
                'sender_name'  => $data['sender_name'] ?? null,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer'   => $request->headers->get('referer'),
            'location'   => null,
        ]);

        if ($article) {
            $article->increment('share_count');
        }

        Log::info('Share email created', ['share_id' => (string) $share->id]);

        return response()->json([
            'ok'      => true,
            'id'      => (string) $share->id,
            'share'   => $share->only(['id','method','platform','article_id']),
            'message' => 'E-mail envoyé et partage enregistré',
        ]);
    }

    /**
     * POST /api/share
     * Payload: article_id, method (email|social|link|embed|print), platform?, url?, meta?
     * Retourne redirect_url (/s/{id}) et external_url (URL de partage finale)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'article_id' => ['required','integer','exists:articles,id'],
            'method'     => ['required','string','in:email,social,link,embed,print'],
            'platform'   => ['nullable','string','max:50'],
            'url'        => ['nullable','url'],
            'meta'       => ['nullable','array'],
        ]);

        Log::debug('share.store.payload', $data);

        $article  = Article::findOrFail($data['article_id']);
        $tenantId = app()->bound('tenant') && app('tenant') ? (app('tenant')->id ?? null) : null;
        $platform = isset($data['platform']) ? strtolower($data['platform']) : null;

        $share = ArticleShare::create([
            'tenant_id'  => $tenantId,
            'article_id' => $article->id,
            'user_id'    => Auth::id(),
            'method'     => ShareMethod::from($data['method']),
            'platform'   => $platform,
            // Pour Facebook: ne pas passer 'url' => laisser le modèle produire l'URL sharer
            'url'        => $platform === 'facebook' ? null : ($data['url'] ?? null),
            'meta'       => $data['meta'] ?? [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer'   => $request->headers->get('referer'),
            'location'   => null,
        ]);

        $external = $share->getShareUrl();
        Log::debug('share.store.computed', [
            'method'   => (string) ($share->method->value ?? $share->method),
            'platform' => $share->platform,
            'external' => $external,
        ]);

        $redirect = route('shares.redirect', ['share' => $share], true);

        return response()->json([
            'ok'           => true,
            'id'           => (string) $share->id,
            'redirect_url' => $redirect,
            'external_url' => $external,
            'message'      => 'Partage enregistré',
        ]);
    }

    /**
     * GET /s/{share}
     * Redirection trackée vers l’URL de partage calculée
     */
    public function redirect(ArticleShare $share, Request $request)
    {
        $share->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer'   => $request->headers->get('referer'),
        ]);

        if ($share->article) {
            $share->article->increment('share_count');
        }

        return redirect()->away($share->getShareUrl());
    }

    /**
     * POST /api/share/{share}/convert
     */
    public function convert(ArticleShare $share)
    {
        $share->markAsConverted();

        return response()->json([
            'ok'           => true,
            'converted_at' => optional($share->converted_at)->toIso8601String(),
            'time_minutes' => $share->getConversionTime(),
        ]);
    }
}
