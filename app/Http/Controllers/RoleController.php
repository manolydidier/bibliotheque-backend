<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;

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

public function index2(Request $request)
    {
        $query = Role::query()->withCount('users');

        // Recherche par nom ou description si un terme est fourni
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        // Tri par nom et récupération de tous les résultats
        $roles = $query->select('id', 'name', 'description', 'is_active', 'created_at')
                      ->orderBy('name')
                      ->get();

        return response()->json($roles);
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
            'is_active' => 'required|boolean', // ✅ Obligatoire, venu du formulaire
        ], [
            'name.required' => 'Le nom du rôle est requis.',
            'name.unique' => 'Un rôle avec ce nom existe déjà.',
            'name.max' => 'Le nom ne peut pas dépasser 50 caractères.',
            'description.max' => 'La description ne peut pas dépasser 500 caractères.',
            'is_active.required' => 'Le statut du rôle doit être spécifié.',
            'is_active.boolean' => 'Le statut doit être vrai ou faux.',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'], // ✅ Valeur choisie manuellement
        ]);

        return response()->json($role, 201);
    }

    /**
     * ✏️ Mettre à jour un rôle existant
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:50|unique:roles,name,' . $id,
            'description' => 'nullable|string|max:500',
            'is_active' => 'required|boolean', // ✅ Toujours requis
            'permissions' => 'array|nullable',
        ], [
            'name.unique' => 'Ce nom est déjà utilisé.',
            'name.max' => 'Le nom ne peut pas dépasser 50 caractères.',
            'description.max' => 'La description ne peut pas dépasser 500 caractères.',
            'is_active.required' => 'Le statut du rôle doit être spécifié.',
            'is_active.boolean' => 'Le statut doit être vrai ou faux.',
        ]);

        // Mise à jour du rôle
        $role->update([
            'name' => $validated['name'] ?? $role->name,
            'description' => $validated['description'] ?? $role->description,
            'is_active' => $validated['is_active'], // ✅ Mis à jour selon le toggle
        ]);

        // Gestion des permissions (si envoyées)
        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']); // Utilise sync pour mettre à jour
        }

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_active' => (bool) $role->is_active,
            'created_at' => $role->created_at,
        ]);
    }

    // 🗑️ Supprimer un rôle
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json(['message' => 'Rôle supprimé']);
    }
}
