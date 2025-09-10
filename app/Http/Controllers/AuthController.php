<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite; // <-- Google OAuth
use Illuminate\Support\Str; // pour Str::random()

class AuthController extends Controller
{
    /**
     * 🔐 Inscription avec token et rôle par défaut
     */
    public function register(Request $request): JsonResponse
    {
        // Normalisation simple -> évite "must be a string"
        $request->merge([
            'username'    => is_string($request->username) ? trim($request->username) : (is_null($request->username) ? null : (string) $request->username),
            'email'       => is_string($request->email) ? trim($request->email) : (is_null($request->email) ? null : (string) $request->email),
            'first_name'  => is_string($request->first_name) ? trim($request->first_name) : (is_null($request->first_name) ? null : (string) $request->first_name),
            'last_name'   => is_string($request->last_name) ? trim($request->last_name) : (is_null($request->last_name) ? null : (string) $request->last_name),
        ]);

        $validator = Validator::make($request->all(), [
            'username'    => 'required|string|max:100|unique:users,username',
            'email'       => 'required|email|max:255|unique:users,email',
            'password'    => 'required|string|min:8|confirmed',
            'first_name'  => 'required|string|max:100',
            'last_name'   => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'username'    => $request->username,
                'email'       => $request->email,
                'password'    => $request->password, // hash via mutator
                'first_name'  => $request->first_name,
                'last_name'   => $request->last_name,
                'is_active'   => true,
            ]);

            $defaultRole = Role::where('name', 'member')->first();
            if ($defaultRole) {
                $user->roles()->attach($defaultRole->id);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message'     => 'Registration success',
                'token'       => $token,
                'token_type'  => 'Bearer',
                'user'        => $user->load('roles'),
                'roles'       => $user->roles,
                'permissions' => method_exists($user, 'permissions') ? $user->permissions() : []
            ], 201);
        } catch (\Exception $e) {
            Log::error('Register error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Server error',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🔓 Connexion avec vérification + token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'error'   => 'The provided credentials are incorrect.'
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Account disabled'
            ], 403);
        }

        $user->last_login = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'     => 'Login success',
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $user->load('roles'),
            'roles'       => $user->roles,
            'permissions' => method_exists($user, 'permissions') ? $user->permissions() : []
        ]);
    }

    /**
     * 🧑‍💻 Récupération de l'utilisateur connecté
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load(['roles']);
            $permissions = method_exists($user, 'permissions') ? $user->permissions() : [];

            return response()->json([
                'user'        => $user,
                'roles'       => $user->roles,
                'permissions' => $permissions
            ]);
        } catch (\Exception $e) {
            Log::error('Fetch user error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error fetching user',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🚪 Déconnexion (révocation du token)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logout success']);
        } catch (\Exception $e) {
            Log::error('Logout error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Logout failed',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🎨 Mise à jour de l'avatar
     */
    public function updateAvatar(Request $request, $id): JsonResponse
    {
        $start = microtime(true);

        $request->validate([
            'avatar_url' => 'required|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        Log::debug('File received', [
            'is_file'        => $request->hasFile('avatar_url'),
            'mime'           => $request->file('avatar_url')?->getMimeType(),
            'size_kb'        => round($request->file('avatar_url')?->getSize() / 1024, 2),
            'original_name'  => $request->file('avatar_url')?->getClientOriginalName(),
        ]);

        $user = User::findOrFail($id);

        if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $path = $request->file('avatar_url')->store("avatars/$id", 'public');
        $user->avatar_url = $path;
        $user->save();

        return response()->json([
            'message'     => 'Avatar updated',
            'avatar_url'  => $user->avatar_url,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);
    }

    /**
     * 🔒 Mise à jour du mot de passe
     */
    public function updatePassword(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => [
                'required', 'string', 'confirmed',
                'different:current_password',
                Password::min(8)->mixedCase()->numbers()->uncompromised(),
            ],
        ]);

        $user = User::findOrFail($userId);

        if (Auth::id() !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password incorrect',
                'errors'  => ['current_password' => ['Incorrect password']]
            ], 422);
        }

        $user->password = $request->password;
        $user->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password updated successfully']);
    }

    /**
     * 🧾 Affichage du profil utilisateur
     */
        public function showProfile(Request $request): JsonResponse
        {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Charger les relations nécessaires
            $user->load('roles');

            return response()->json([
                'user'        => $user,
                'roles'       => $user->roles,
                'permissions' => method_exists($user, 'permissions') ? $user->permissions() : []
            ]);
        }

    /**
     * 📝 Mise à jour de profil (avec détection de duplicats)
     */
    protected function performProfileUpdate(User $user, array $validated)
    {
        $updateData = $validated;
        $changesDetected = false;

        foreach ($validated as $field => $value) {
            if ($user->{$field} == $value) {
                unset($updateData[$field]);
            } else {
                $changesDetected = true;

                if (in_array($field, ['email', 'username'])) {
                    $exists = User::where($field, $value)
                                  ->where('id', '!=', $user->id)
                                  ->exists();

                    if ($exists) {
                        throw ValidationException::withMessages([
                            $field => ["This $field is already taken by another user"]
                        ]);
                    }
                }
            }
        }

        if (!$changesDetected) {
            return [
                'message' => 'No changes detected (identical values ignored)',
                'user' => $this->getUserProfileData($user)
            ];
        }

        Log::info('Profile update triggered', [
            'user_id' => $user->id,
            'payload' => $updateData
        ]);

        $user->update($updateData);

        return [
            'message' => 'Profile updated successfully',
            'changes' => $user->getChanges(),
            'user' => $this->getUserProfileData($user->fresh())
        ];
    }

    public function updateProfile(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if (Auth::id() != $userId) {
            return response()->json([
                'message' => 'Unauthorized: You can only update your own profile'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => [
                'sometimes', 'required', 'string', 'max:191',
                Rule::unique('users')->ignore($user->id)
            ],
            'email' => [
                'sometimes', 'required', 'email', 'max:191',
                Rule::unique('users')->ignore($user->id)
            ],
            'first_name' => 'sometimes|required|string|max:100',
            'last_name'  => 'sometimes|required|string|max:100',
            'phone'      => 'nullable|string|max:20',
            'address'    => 'sometimes|nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error','errors' => $validator->errors()], 422);
        }

        try {
            $response = $this->performProfileUpdate($user, $validator->validated());
            return response()->json($response);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation error','errors' => $e->errors()], 422);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update profile','error' => $e->getMessage()], 500);
        }
    }

    protected function getUserProfileData(User $user)
    {
        return [
            'id'            => $user->id,
            'username'      => $user->username,
            'email'         => $user->email,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'full_name'     => $user->name,
            'phone'         => $user->phone,
            'address'       => $user->address,
            'date_of_birth' => $user->date_of_birth,
            'is_active'     => $user->is_active,
            'email_verified'=> $user->email_verified,
            'last_login'    => $user->last_login,
            'roles'         => $user->roles->pluck('name'),
            'permissions'   => method_exists($user, 'permissions') ? $user->permissions()->pluck('name') : collect()
        ];
    }

    // fetch all users with roles and permissions with pagination
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $users = User::with(['roles'])
            ->select(['id','first_name','last_name','email','is_active','last_login','created_at','avatar_url'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id'            => $user->id,
                    'name'          => $user->first_name . ' ' . $user->last_name,
                    'email'         => $user->email,
                    'role'          => $user->roles->first()->name ?? 'User',
                    'status'        => $user->is_active ? 'Actif' : 'Inactif',
                    'last_activity' => $user->last_login ? $user->last_login->diffForHumans() : 'Jamais',
                    'avatar_url'    => $user->avatar_url
                ];
            }),
            'pagination' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ]
        ]);
    }

    // 🔐 Soft delete
    public function delete($id) {
        $user = User::find($id);
        if (! $user) return response()->json(['message' => 'Utilisateur introuvable'], 404);
        $user->delete();
        return response()->json(['message' => 'Utilisateur désactivé'], 200);
    }

    // ♻️ Restore
    public function restore($id) {
        $user = User::withTrashed()->find($id);
        if (! $user || ! $user->trashed()) return response()->json(['message' => 'Utilisateur non supprimé'], 404);
        $user->restore();
        return response()->json(['message' => 'Utilisateur restauré'], 200);
    }

    // 🧨 Force delete
    public function forceDelete($id) {
        $user = User::withTrashed()->find($id);
        if (! $user) return response()->json(['message' => 'Utilisateur introuvable'], 404);
        $user->forceDelete();
        return response()->json(['message' => 'Utilisateur supprimé définitivement'], 200);
    }

    // 📋 Deleted list
    public function listDeleted() {
        $deletedUsers = User::onlyTrashed()->get();
        return response()->json($deletedUsers);
    }

    // update active flag
    public function activate($id) {
        $user = User::find($id);
        if (! $user) return response()->json(['message' => 'Utilisateur introuvable'], 404);
        $user->is_active = 1; $user->save();
        return response()->json(['message' => 'Utilisateur activé'], 200);
    }

    public function deactivate($id) {
        $user = User::find($id);
        if (! $user) return response()->json(['message' => 'Utilisateur introuvable'], 404);
        $user->is_active = 0; $user->save();
        return response()->json(['message' => 'Utilisateur désactivé'], 200);
    }

    /* =========================
     *   ✅ Validation unique
     * ========================= */
    public function validateUnique(Request $request): JsonResponse
    {
        $field    = $request->query('field');
        $value    = (string) $request->query('value', '');
        $ignoreId = $request->query('ignore_id');

        if (!in_array($field, ['email','username'], true)) {
            return response()->json(['unique' => false, 'message' => 'Unsupported field'], 422);
        }

        $q = User::query()->where($field, $value);
        if ($ignoreId) $q->where('id', '!=', $ignoreId);
        $exists = $q->exists();

        return response()->json([
            'unique'  => !$exists,
            'message' => $exists
                ? ($field === 'email' ? 'Cet email est déjà utilisé.' : 'Ce nom d’utilisateur est déjà pris.')
                : ''
        ]);
    }

    /* =========================
     *   🔵 Google OAuth (popup)
     * ========================= */
    public function googleRedirect(Request $request): JsonResponse
    {
        // Socialite sans état (Sanctum reste stateless ici)
        $redirect = Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account', 'access_type' => 'online'])
            ->redirect();

        return response()->json([
            'url'   => $redirect->getTargetUrl(),
            'popup' => true,
        ]);
    }

    public function googleCallback(Request $request)
    {
        $frontend = config('app.frontend_url', env('FRONTEND_URL', '*'));

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $email    = $googleUser->getEmail();
            $googleId = $googleUser->getId();
            $avatar   = $googleUser->getAvatar();
            $fullName = $googleUser->getName() ?: '';
            [$first, $last] = array_pad(explode(' ', $fullName, 2), 2, '');

            $user = User::where('google_id', $googleId)->first();
            if (!$user && $email) {
                $user = User::where('email', $email)->first();
            }

            if ($user) {
                if (!$user->google_id) {
                    $user->google_id    = $googleId;
                    $user->google_email = $email;
                }
                if (!$user->avatar_url && $avatar) $user->avatar_url = $avatar;
                if (!$user->first_name && $first)  $user->first_name = $first;
                if (!$user->last_name  && $last)   $user->last_name  = $last;
                $user->save();
            } else {
                $username = $email ? strstr($email, '@', true) : ('google_' . substr($googleId, -6));
                $base = $username; $i = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $base . $i++;
                }

                $user = User::create([
                    'username'     => $username,
                    'email'        => $email,
                    'password'     => bcrypt(Str::random(32)),
                    'first_name'   => $first,
                    'last_name'    => $last,
                    'is_active'    => true,
                    'google_id'    => $googleId,
                    'google_email' => $email,
                    'avatar_url'   => $avatar,
                ]);

                if (class_exists(Role::class)) {
                    $defaultRole = Role::where('name', 'member')->first();
                    if ($defaultRole) $user->roles()->attach($defaultRole->id);
                }
            }

            $user->last_login = now();
            $user->save();

            $token = $user->createToken('auth_token')->plainTextToken;

            $payload = [
                'message'     => 'Login success',
                'token'       => $token,
                'token_type'  => 'Bearer',
                'user'        => $user->load('roles'),
                'roles'       => $user->roles,
                'permissions' => method_exists($user, 'permissions') ? $user->permissions() : [],
            ];
            $json = e(json_encode($payload));

            // page HTML qui envoie un postMessage vers le parent, puis se ferme
            return response(
                "<!doctype html><html><head><meta charset='utf-8'><title>Google OAuth</title></head><body>
                  <script>
                    (function(){
                      try{
                        var data = JSON.parse('{$json}');
                        if (window.opener) {
                          window.opener.postMessage({ source: 'google-oauth', data: data }, '{$frontend}');
                          window.close();
                        } else {
                          location.href = '{$frontend}'.replace(/\\/\$/, '') + '/#oauth=google&token=' + encodeURIComponent(data.token);
                        }
                      }catch(e){ document.body.innerText = 'OAuth done. You can close this window.'; }
                    })();
                  </script>
                </body></html>",
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback error', ['e' => $e->getMessage()]);

            return response(
                "<!doctype html><html><body>
                   <script>
                     if (window.opener) {
                       window.opener.postMessage({ source: 'google-oauth', error: '".e($e->getMessage())."' }, '{$frontend}');
                       window.close();
                     } else { document.body.innerText = 'OAuth failed. You can close this window.'; }
                   </script>
                 </body></html>",
                500,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }
}
