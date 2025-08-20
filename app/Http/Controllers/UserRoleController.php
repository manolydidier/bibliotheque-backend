<?php

namespace App\Http\Controllers;

use App\Models\UserRole;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    /**
     * Afficher la liste des attributions de rôles
     */
   public function index(Request $request): JsonResponse
{
    // Construire la requête avec les relations
    $query = UserRole::with(['user', 'role', 'assignedBy']);

    // 🔍 Recherche globale
    if ($request->filled('search')) {
        $search = $request->search;
        $query->whereHas('user', function ($q) use ($search) {
            $q->where('username', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");
        })->orWhereHas('role', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // 🎯 Filtres optionnels
    if ($request->has('user_id')) {
        $query->forUser($request->user_id);
    }

    if ($request->has('role_id')) {
        $query->forRole($request->role_id);
    }

    // 📄 Pagination
    $perPage = $request->get('per_page', 10);
    $userRoles = $query->latest()->paginate($perPage);

    return response()->json([
        'status' => 'success',
        'data' => $userRoles, // Laravel gère automatiquement la pagination
    ]);
}

    /**
     * Créer une nouvelle attribution de rôle
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => [
                'required',
                'exists:roles,id',
                Rule::unique('user_roles')->where(function ($query) use ($request) {
                    return $query->where('user_id', $request->user_id);
                }),
            ],
            'assigned_by' => 'nullable|exists:users,id',
            'assigned_at' => 'nullable|date|before_or_equal:now',
        ], [
            'user_id.required' => 'L\'ID de l\'utilisateur est obligatoire.',
            'user_id.exists' => 'L\'utilisateur spécifié n\'existe pas.',
            'role_id.required' => 'L\'ID du rôle est obligatoire.',
            'role_id.exists' => 'Le rôle spécifié n\'existe pas.',
            'role_id.unique' => 'Cet utilisateur a déjà ce rôle.',
            'assigned_by.exists' => 'L\'utilisateur qui assigne le rôle n\'existe pas.',
            'assigned_at.date' => 'La date d\'attribution doit être une date valide.',
            'assigned_at.before_or_equal' => 'La date d\'attribution ne peut pas être dans le futur.',
        ]);

        // Définir assigned_at si pas fourni
        if (!isset($validated['assigned_at'])) {
            $validated['assigned_at'] = now();
        }

        if (!isset($validated['assigned_by']) && Auth::check()) {
            $validated['assigned_by'] = Auth::id();
         
        }

        try {
            $userRole = UserRole::create($validated);
            $userRole->load(['user', 'role', 'assignedBy']);

            return response()->json([
                'status' => 'success',
                'message' => 'Attribution de rôle créée avec succès.',
                'data' => $userRole,
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de l\'attribution.',
            ], 500);
        }
    }

    /**
     * Afficher une attribution de rôle spécifique
     */
    public function show($id): JsonResponse
    {
        $userRole = UserRole::with(['user', 'role', 'assignedBy'])->find($id);

        if (!$userRole) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attribution de rôle non trouvée.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $userRole,
        ]);
    }

    /**
     * Mettre à jour une attribution de rôle
     */
    public function update(Request $request, $id): JsonResponse
{
    $userRole = UserRole::find($id);

    if (!$userRole) {
        return response()->json([
            'status' => 'error',
            'message' => 'Attribution de rôle non trouvée.',
        ], 404);
    }

    $validated = $request->validate([
        'user_id' => 'sometimes|required|exists:users,id',
        'role_id' => [
            'required',
            'exists:roles,id',
            Rule::unique('user_roles')->where(function ($query) use ($request, $userRole) {
                $userId = $request->user_id ?? $userRole->user_id;
                return $query->where('user_id', $userId);
            })->ignore($id),
        ],
        'assigned_by' => 'nullable|exists:users,id',
        'assigned_at' => 'nullable|date|before_or_equal:now',
    ], [
        'user_id.exists' => 'L\'utilisateur spécifié n\'existe pas.',
        'role_id.exists' => 'Le rôle spécifié n\'existe pas.',
        'role_id.unique' => 'Cet utilisateur a déjà ce rôle.',
        'assigned_by.exists' => 'L\'utilisateur qui assigne le rôle n\'existe pas.',
        'assigned_at.date' => 'La date d\'attribution doit être une date valide.',
        'assigned_at.before_or_equal' => 'La date d\'attribution ne peut pas être dans le futur.',
    ]);

    try {
        // Supprimer tous les rôles existants de l'utilisateur
        UserRole::where('user_id', $validated['user_id'] ?? $userRole->user_id)
                ->delete();

        // Créer la nouvelle attribution de rôle
        $newUserRole = UserRole::create([
            'user_id' => $validated['user_id'] ?? $userRole->user_id,
            'role_id' => $validated['role_id'],
            'assigned_by' => $validated['assigned_by'] ?? Auth::id(),
            'assigned_at' => $validated['assigned_at'] ?? now(),
        ]);

        // Charger les relations
        $newUserRole->load(['user', 'role', 'assignedBy']);

        return response()->json([
            'status' => 'success',
            'message' => 'Rôle mis à jour avec succès.',
            'data' => $newUserRole,
        ]);

    } catch (QueryException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Erreur lors de la mise à jour du rôle.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}

    /**
     * Supprimer une attribution de rôle
     */
    public function destroy($id): JsonResponse
    {
        $userRole = UserRole::find($id);

        if (!$userRole) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attribution de rôle non trouvée.',
            ], 404);
        }

        try {
            $userRole->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Attribution de rôle supprimée avec succès.',
            ]);

        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression.',
            ], 500);
        }
    }

    /**
     * Obtenir tous les rôles d'un utilisateur
     */
    public function getUserRoles($userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        $userRoles = UserRole::with(['role', 'assignedBy'])
            ->forUser($userId)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'roles' => $userRoles,
            ],
        ]);
    }

    /**
     * Obtenir tous les utilisateurs ayant un rôle spécifique
     */
    public function getRoleUsers($roleId): JsonResponse
    {
        $role = Role::find($roleId);

        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rôle non trouvé.',
            ], 404);
        }

        $roleUsers = UserRole::with(['user', 'assignedBy'])
            ->forRole($roleId)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'role' => $role,
                'users' => $roleUsers,
            ],
        ]);
    }

    /**
     * Attribution en lot de rôles
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => 'required|array|min:1|max:100',
            'assignments.*.user_id' => 'required|exists:users,id',
            'assignments.*.role_id' => 'required|exists:roles,id',
            'assigned_by' => 'nullable|exists:users,id',
        ], [
            'assignments.required' => 'Les attributions sont obligatoires.',
            'assignments.array' => 'Les attributions doivent être un tableau.',
            'assignments.min' => 'Au moins une attribution est requise.',
            'assignments.max' => 'Maximum 100 attributions par lot.',
            'assignments.*.user_id.required' => 'L\'ID utilisateur est obligatoire pour chaque attribution.',
            'assignments.*.user_id.exists' => 'Un des utilisateurs spécifiés n\'existe pas.',
            'assignments.*.role_id.required' => 'L\'ID rôle est obligatoire pour chaque attribution.',
            'assignments.*.role_id.exists' => 'Un des rôles spécifiés n\'existe pas.',
            'assigned_by.exists' => 'L\'utilisateur qui assigne les rôles n\'existe pas.',
        ]);

        // Définir assigned_by depuis l'utilisateur authentifié si non fourni
        if (!isset($validated['assigned_by']) && Auth::check()) {
            $validated['assigned_by'] = Auth::id();
        }

        $successCount = 0;
        $errors = [];

        foreach ($validated['assignments'] as $index => $assignment) {
            try {
                // Vérifier si l'attribution existe déjà
                $exists = UserRole::where('user_id', $assignment['user_id'])
                    ->where('role_id', $assignment['role_id'])
                    ->exists();

                if (!$exists) {
                    UserRole::create([
                        'user_id' => $assignment['user_id'],
                        'role_id' => $assignment['role_id'],
                        'assigned_by' => $validated['assigned_by'] ?? null,
                        'assigned_at' => now(),
                    ]);
                    $successCount++;
                } else {
                    $errors[] = "Attribution #{$index}: déjà existante";
                }
            } catch (QueryException $e) {
                $errors[] = "Attribution #{$index}: erreur de création";
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$successCount} attribution(s) créée(s) avec succès.",
            'success_count' => $successCount,
            'errors' => $errors,
        ]);
    }
}