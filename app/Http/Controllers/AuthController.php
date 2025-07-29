<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Inscription avec rôle par défaut et token
     */
   public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'username'    => 'required|string|max:100',
        'email'       => 'required|string|email|max:255',
        'password'    => 'required|string|min:8|confirmed',
        'first_name'  => 'required|string|max:100',
        'last_name'   => 'required|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Échec de validation',
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        // Pas de Hash::make ici — le mutator s’en charge automatiquement
        $user = new User([
            'username'    => $request->username,
            'email'       => $request->email,
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'is_active'   => true,
            'password'    => $request->password, // 🧠 le mutator hashera automatiquement
        ]);

        $user->save();

        $user->assignRole('member'); // rôle par défaut

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'     => 'Inscription réussie',
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $user->load('roles'),
            'roles'       => $user->roles,
            'permissions' => method_exists($user, 'permissions') ? $user->permissions() : []
        ], 201);
    } catch (\Exception $e) {
        Log::error('Erreur inscription: '.$e->getMessage());
        return response()->json([
            'message' => 'Erreur serveur',
            'error'   => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Connexion avec vérification des identifiants et du statut actif
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Échec de validation',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects',
                'error'   => 'The provided credentials are incorrect.'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Compte désactivé'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'     => 'Connexion réussie',
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $user->load('roles'), // ✅ relations seulement
            'roles'       => $user->roles,
            'permissions' => method_exists($user, 'permissions') ? $user->permissions() : [] // ✅ méthode personnalisée
        ]);

    }

    /**
     * Récupération de l'utilisateur connecté
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user()->load(['roles', 'permissions']);

            return response()->json([
                'user'        => $user,
                'roles'       => $user->roles,
                'permissions' => method_exists($user, 'permissions') ? $user->permissions() : []
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération user: '.$e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des données',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Déconnexion (révocation du token)
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Déconnexion réussie'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur déconnexion: '.$e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la déconnexion',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}