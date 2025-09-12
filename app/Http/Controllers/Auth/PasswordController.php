<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * POST /api/auth/forgot-password
     * Body: email, langue? (fr|en)
     */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email'  => 'required|email',
            'langue' => 'nullable|string|in:fr,en',
        ]);

        $langue = $request->input('langue', 'fr');

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker(); // <- Typé pour calmer l’IDE

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Token de réinitialisation
            $token = $broker->createToken($user);

            // Envoi de la notification (email bleu)
            $user->notify(new ResetPasswordLink(
                token: $token,
                email: $user->email,
                langue: $langue
            ));
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Si un compte existe, un lien de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * POST /api/auth/reset-password
     * Body: email, token, password, password_confirmation
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();

        $status = $broker->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // Ton mutator User::setPasswordAttribute hash déjà -> pas de Hash::make ici
                $user->password = $password;
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'ok'      => true,
                'message' => 'Mot de passe réinitialisé.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
