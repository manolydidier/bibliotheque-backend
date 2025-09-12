<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    private const CODE_TTL_MIN        = 10;   // validité du code (minutes)
    private const RESEND_COOLDOWN_SEC = 60;   // délai mini entre 2 envois (secondes)
    private const MAX_VERIFY_ATTEMPTS = 5;    // tentatives max avant reset

    /** GET /api/email/exists?email=... */
    public function exists(Request $request): JsonResponse
    {
        $rules = app()->environment('local')
            ? ['email' => ['required','email:rfc','max:255']]
            : ['email' => ['required','email:rfc,dns','max:255']];

        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $v->errors()], 422);
        }

        $email  = Str::lower((string) $request->input('email'));
        $exists = User::query()->where('email', $email)->exists();

        return response()->json(['exists' => $exists]);
    }

    /** POST /api/email/verification/request  { email, lang?, intent? } */
    public function requestCode(Request $request): JsonResponse
    {
        $rules = app()->environment('local')
            ? ['email' => ['required','email:rfc','max:255']]
            : ['email' => ['required','email:rfc,dns','max:255']];

        $v = Validator::make($request->all(), [
            'email'  => $rules['email'],
            'lang'   => ['nullable','string','in:fr,en'],
            'intent' => ['nullable','string','in:login,register,other'],
        ]);

        if ($v->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $v->errors()], 422);
        }

        $email  = Str::lower((string) $request->input('email'));
        $lang   = $request->input('lang', 'fr');
        $intent = $request->input('intent', 'login');

        if ($intent !== 'register' && !User::query()->where('email', $email)->exists()) {
            return response()->json([
                'message' => $lang === 'fr' ? "Cet e-mail n'est pas reconnu." : 'Email not found.',
            ], 404);
        }

        $cooldownKey = $this->cooldownKey($email);
        if (Cache::has($cooldownKey)) {
            $ttl = (int)Cache::get($cooldownKey) - time();
            return response()->json([
                'message'  => $lang === 'fr' ? 'Veuillez patienter avant de redemander un code.' : 'Please wait before requesting another code.',
                'retry_in' => max(1, $ttl),
            ], 429);
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otpKey = $this->otpKey($email);
        Cache::put($otpKey, [
            'code'      => $code,
            'attempts'  => 0,
            'generated' => Carbon::now()->toIso8601String(),
            'intent'    => $intent,
        ], now()->addMinutes(self::CODE_TTL_MIN));

        Cache::put($cooldownKey, time() + self::RESEND_COOLDOWN_SEC, now()->addSeconds(self::RESEND_COOLDOWN_SEC));

        $subject = $lang === 'fr' ? 'Votre code de vérification' : 'Your verification code';
        $ttlTxt  = $lang === 'fr'
            ? 'Ce code est valable ' . self::CODE_TTL_MIN . ' minutes.'
            : 'This code is valid for ' . self::CODE_TTL_MIN . ' minutes.';

        // Données pour la vue Blade
        $viewData = [
            'lang'        => $lang,
            'code'        => $code,
            'ttlMinutes'  => self::CODE_TTL_MIN,
            'ttlText'     => $ttlTxt,
            'email'       => $email,
            'intent'      => $intent,
            'now'         => now(),
            'appName'     => config('app.name'),
            // Lien de CTA optionnel (page de connexion par ex.)
            'ctaUrl'      => config('app.url'),
            'supportMail' => config('mail.from.address'),
        ];

        try {
            // Envoi HTML via Blade (au lieu de Mail::raw)
            Mail::send('emails.otp', $viewData, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OTP mail send failed', ['email' => $email, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => $lang === 'fr' ? "Échec d'envoi du code." : 'Failed to send code.',
            ], 500);
        }

        $payload = ['success' => true, 'ttl' => self::CODE_TTL_MIN * 60];

        if (app()->environment('local')) {
            // Debug très pratique en dev
            $payload['dev_code'] = $code;
            Log::info('OTP dev_code', ['email' => $email, 'code' => $code]);
        }

        return response()->json($payload, 200);
    }

    /** POST /api/email/verification/confirm  { email, code, lang?, intent?, mark_user_as_verified? } */
    public function confirm(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'email'                 => ['required','email:rfc','max:255'],
            'code'                  => ['required','digits:6'],
            'lang'                  => ['nullable','string','in:fr,en'],
            'intent'                => ['nullable','string','in:login,register,other'],
            'mark_user_as_verified' => ['nullable','boolean'],
        ]);

        if ($v->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $v->errors()], 422);
        }

        $email  = Str::lower((string)$request->input('email'));
        $code   = (string)$request->input('code');
        $lang   = $request->input('lang', 'fr');
        $mark   = (bool)$request->boolean('mark_user_as_verified', false);

        $otpKey = $this->otpKey($email);
        $data   = Cache::get($otpKey);

        if (!$data) {
            return response()->json([
                'verified' => false,
                'message'  => $lang === 'fr' ? 'Code expiré ou introuvable. Redemandez un nouveau code.' : 'Code expired or not found. Please request a new code.',
            ], 422);
        }

        $attempts = (int)($data['attempts'] ?? 0);

        if (!hash_equals($data['code'], $code)) {
            $attempts++;
            Cache::put($otpKey, array_merge($data, ['attempts' => $attempts]), now()->addMinutes(self::CODE_TTL_MIN));

            if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
                Cache::forget($otpKey);
                return response()->json([
                    'verified' => false,
                    'message'  => $lang === 'fr' ? 'Trop de tentatives. Code réinitialisé, veuillez redemander un code.' : 'Too many attempts. Code reset, please request a new one.',
                ], 429);
            }

            return response()->json([
                'verified'      => false,
                'message'       => $lang === 'fr' ? 'Code incorrect.' : 'Incorrect code.',
                'attempts_left' => max(0, self::MAX_VERIFY_ATTEMPTS - $attempts),
            ], 422);
        }

        // OK
        Cache::forget($otpKey);

        if ($mark) {
            $user = User::query()->where('email', $email)->first();
            if ($user && is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
                $user->save();
            }
        }

        Cache::put($this->verifiedFlagKey($email), true, now()->addMinutes(30));

        return response()->json([
            'verified' => true,
            'message'  => $lang === 'fr' ? 'E-mail vérifié.' : 'Email verified.',
        ], 200);
    }

    private function otpKey(string $email): string          { return 'otp:verify:'   . md5(Str::lower($email)); }
    private function cooldownKey(string $email): string     { return 'otp:cooldown:' . md5(Str::lower($email)); }
    private function verifiedFlagKey(string $email): string { return 'otp:verified:' . md5(Str::lower($email)); }
}
