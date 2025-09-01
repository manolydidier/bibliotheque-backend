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
    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email'  => 'required|email',
            'langue' => 'nullable|string|in:fr,en',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Password::createToken($user);
            $user->notify(new ResetPasswordLink(
                token: $token,
                email: $user->email,
                langue: $request->input('langue', 'fr'),
            ));
        }

        return response()->json([
            'message' => 'Si un compte existe, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // ⚠️ ICI: pas de Hash::make — ton mutator va hasher.
                $user->password = $password;
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Mot de passe réinitialisé.']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
