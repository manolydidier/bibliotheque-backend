<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

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

        $role = Role::where('name', 'member')->first();

        if ($role) {
            $user->roles()->attach($role->id);
        }

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

    /**
     * Mise à jour de l'avatar de l'utilisateur connecté
     */


  public function updateAvatar(Request $request, $id)
{
    
    $start = microtime(true);

    $request->validate([
        'avatar_url' => 'required|image|mimes:jpeg,png,jpg|max:2048'
    ]);

    // 🔍 Log debug réception fichier
    Log::debug('Fichier reçu', [
        'is_file' => $request->hasFile('avatar_url'),
        'mime'    => $request->file('avatar_url')?->getMimeType(),
        'size_kb' => round($request->file('avatar_url')?->getSize() / 1024, 2),
        'original_name' => $request->file('avatar_url')?->getClientOriginalName()
    ]);

    $user = User::findOrFail($id);

    // 🔥 Purge éventuelle de l’ancien avatar
    if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
        Storage::disk('public')->delete($user->avatar_url);
    }

    // 📂 Stockage du nouveau fichier
    $path = $request->file('avatar_url')->store("avatars/{$id}", 'public');
    $user->avatar_url = $path;
    $user->save();

    $duration = round((microtime(true) - $start) * 1000, 2);

    // 📝 Log de succès
    Log::info('updateAvatar', [
        'user_id'     => $user->id,
        'avatar_path' => $path,
        'duration_ms' => $duration,
        'source'      => 'upload fichier'
    ]);

    return response()->json([
        'message'     => 'Avatar mis à jour avec fichier',
        'avatar_url'  => $user->avatar_url,
        'duration_ms' => $duration
    ]);
}



public function updatePassword(Request $request, $userId)
{
    $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::findOrFail($userId);

    //  Vérification de l'autorisation
    //  if (Auth::id() != $user->id) {
    //     return response()->json([
    //      'message' => __('Unauthorized action'),
    //     ], 403);
    // }

    // Vérification du mot de passe actuel
    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'message' => __('The current password is incorrect'),
            'errors' => ['current_password' => [__('The current password is incorrect')]]
        ], 422);
    }

    // Mise à jour du mot de passe (le mutator s'occupe du hachage)
    $user->password = $request->password; // Le mutator setPasswordAttribute fera le Hash
    $user->save();

    // Révoquer tous les tokens existants (important pour Sanctum)
    $user->tokens()->delete();

    return response()->json([
        'message' => __('Password updated successfully'),
    ]);
}

}


