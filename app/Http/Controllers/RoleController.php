<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
   
    // 📄 Lister tous les rôles avec leurs permissions
   // RoleController.php
public function index(Request $request)
{
    $query = Role::withCount('users');

    // Recherche par nom ou description
    if ($request->filled('search')) {
        $query->where('name', 'like', '%' . $request->search . '%')
              ->orWhere('description', 'like', '%' . $request->search . '%');
    }

    $roles = $query->select('id', 'name', 'description', 'is_active', 'created_at')
        ->orderBy('name')
        ->paginate(10); // 10 par page

    return response()->json($roles);
}          

/**
     * Récupère la liste des rôles avec pagination, tri et filtres
     */
    public function index2(Request $request)
    {
        $query = Role::query()->withCount('users');

        // Recherche par nom ou description
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filtre pour les rôles admin seulement il faut ajouter un colonne admin pour simplifier ceci.
        if ($request->boolean('is_admin')) {
            $query->where('is_admin', true);
        }

        // Filtre pour les rôles avec permissions
        if ($request->boolean('has_permissions')) {
            $query->whereHas('permissions');
        }

        // Tri
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        
        // Validation des champs de tri
        $allowedSortFields = ['name', 'users_count', 'created_at'];
        $sortField = in_array($sortField, $allowedSortFields) ? $sortField : 'name';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'asc';
        
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $perPage = min(max($perPage, 5), 100); // Limite entre 5 et 100 items par page
        
        $roles = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $roles->items(),
            'meta' => [
                'total' => $roles->total(),
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'last_page' => $roles->lastPage(),
            ]
        ]);
    }

    /**
     * Met à jour le rôle d'un utilisateur
     */
    public function updateUserRole(Request $request, $userId)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($userId);
        $user->role_id = $request->role_id;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'user' => $user->load('role')
        ]);
    }

    // 🔎 Voir un rôle précis
    public function show($id)
    {
        return Role::with('permissions')->findOrFail($id);
    }

    // 🆕 Créer un nouveau rôle avec permissions
   public function store(Request $request)
   {
    $validated = $request->validate([
        'name' => 'required|string|max:50|unique:roles,name',
        'description' => 'nullable|string|max:500',
        'is_active' => 'required|boolean',
        'is_admin' => 'required|boolean', // ✅ Ajouté
    ], [
        'name.required' => 'Le nom du rôle est requis.',
        'name.unique' => 'Un rôle avec ce nom existe déjà.',
        'name.max' => 'Le nom ne peut pas dépasser 50 caractères.',
        'description.max' => 'La description ne peut pas dépasser 500 caractères.',
        'is_active.required' => 'Le statut du rôle doit être spécifié.',
        'is_active.boolean' => 'Le statut doit être vrai ou faux.',
        'is_admin.required' => 'Le type administrateur doit être spécifié.',
        'is_admin.boolean' => 'Le type administrateur doit être vrai ou faux.',
    ]);

    $role = Role::create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'is_active' => $validated['is_active'],
        'is_admin' => $validated['is_admin'], // ✅ Ajouté
    ]);

    return response()->json($role, 201);
}

    /**
     * ✏️ Mettre à jour un rôle existant
     */
    public function update(Request $request, $id)
{
    // Trouver le rôle ou échouer
    $role = Role::findOrFail($id);

    // Validation des données
    $validated = $request->validate([
        'name' => [
            'sometimes', // Valide seulement si présent
            'required',
            'string',
            'max:50',
            Rule::unique('roles', 'name')->ignore($id), // Ignore l'ID actuel
        ],
        'description' => 'nullable|string|max:500',
        'is_active' => 'required|boolean', // ✅ Obligatoire
        'is_admin' => 'required|boolean', // ✅ Ajouté : obligatoire
    ], [
        'name.required' => 'Le nom du rôle est requis.',
        'name.unique' => 'Un rôle avec ce nom existe déjà.',
        'name.max' => 'Le nom ne peut pas dépasser 50 caractères.',
        'description.max' => 'La description ne peut pas dépasser 500 caractères.',
        'is_active.required' => 'Le statut du rôle doit être spécifié.',
        'is_active.boolean' => 'Le statut doit être vrai ou faux.',
        'is_admin.required' => 'Le type administrateur doit être spécifié.',
        'is_admin.boolean' => 'Le type administrateur doit être vrai ou faux.',
    ]);

    // Mise à jour du rôle
    $role->update([
        'name' => $validated['name'] ?? $role->name,
        'description' => $validated['description'] ?? null,
        'is_active' => $validated['is_active'],
        'is_admin' => $validated['is_admin'], // ✅ Ajouté
    ]);

    // Réponse JSON
    return response()->json([
        'id' => $role->id,
        'name' => $role->name,
        'description' => $role->description,
        'is_active' => (bool) $role->is_active,
        'is_admin' => (bool) $role->is_admin,
        'created_at' => $role->created_at,
        'updated_at' => $role->updated_at,
    ], 200);
}

    // 🗑️ Supprimer un rôle
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json(['message' => 'Rôle supprimé']);
    }
}
