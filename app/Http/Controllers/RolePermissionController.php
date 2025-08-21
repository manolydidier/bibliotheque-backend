<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\RolePermission;

class RolePermissionController extends Controller
{
    /**
     * Liste paginée avec recherche et filtres.
     */
    public function index(Request $request)
    {
        $query = RolePermission::with('role', 'permission', 'grantedBy');

        // Filtre par role_id
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filtre par permission_id
        if ($request->filled('permission_id')) {
            $query->where('permission_id', $request->permission_id);
        }

        // Filtre par utilisateur ayant accordé la permission
        if ($request->filled('granted_by')) {
            $query->where('granted_by', $request->granted_by);
        }

        // Recherche par nom de rôle ou nom de permission
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('role', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhereHas('permission', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Tri (par défaut par granted_at décroissant)
        $sort = $request->input('sort', 'granted_at');
        $order = $request->input('order', 'desc');

        // Sécurité : liste blanche des champs triables
        $allowedSorts = ['granted_at', 'created_at', 'updated_at'];
        $sortColumn = in_array($sort, $allowedSorts) ? $sort : 'granted_at';

        $query->orderBy($sortColumn, $order);

        // Pagination (10 par page par défaut)
        $perPage = $request->input('per_page', 10);
        $perPage = ($perPage > 100) ? 100 : $perPage; // Limite max

        $rolePermissions = $query->paginate($perPage);

        return response()->json([
            'data' => $rolePermissions->items(),
            'pagination' => [
                'total' => $rolePermissions->total(),
                'current_page' => $rolePermissions->currentPage(),
                'last_page' => $rolePermissions->lastPage(),
                'per_page' => $rolePermissions->perPage(),
                'from' => $rolePermissions->firstItem(),
                'to' => $rolePermissions->lastItem(),
            ]
        ], 200);
    }

    /**
     * Attribue une permission à un rôle.
     */
   public function store(Request $request)
{
    // Debug - vérifiez ce qui est reçu
    \Log::info('Données reçues:', $request->all());
    
    $validated = $request->validate([
        'role_id' => 'required|integer|exists:roles,id',
        'permission_id' => 'required|integer|exists:permissions,id',
        'granted_by' => 'nullable|integer|exists:users,id'
    ]);

    // Vérifiez que l'association n'existe pas déjà
    $exists = RolePermission::where('role_id', $validated['role_id'])
                           ->where('permission_id', $validated['permission_id'])
                           ->exists();
    
    if ($exists) {
        return response()->json([
            'status' => 'error',
            'message' => 'This permission is already assigned to this role'
        ], 422);
    }

    try {
        $rolePermission = RolePermission::create([
            'role_id' => $validated['role_id'],
            'permission_id' => $validated['permission_id'],
            'granted_by' => $validated['granted_by'] ?? auth()->id(),
            'granted_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission assigned to role successfully',
            'data' => $rolePermission->load('role', 'permission', 'grantedBy')
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur création role_permission:', ['error' => $e->getMessage()]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to assign permission to role'
        ], 500);
    }
}

    /**
     * Affiche une association spécifique.
     */
    public function show($id)
    {
        try {
            $rolePermission = RolePermission::with('role', 'permission', 'grantedBy')->findOrFail($id);
            return response()->json($rolePermission, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Association non trouvée.'], 404);
        }
    }

    /**
     * Supprime une association rôle-permission.
     */
    public function destroy($id)
    {
        try {
            $rolePermission = RolePermission::findOrFail($id);
            $rolePermission->delete();

            return response()->json(['message' => 'Permission retirée du rôle avec succès.'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Association non trouvée.'], 404);
        }
    }

    /**
     * Liste les permissions d'un rôle avec recherche et pagination.
     */
    public function permissionsByRole(Request $request, $roleId)
    {
        $role = Role::find($roleId);
        if (!$role) {
            return response()->json(['message' => 'Rôle non trouvé.'], 404);
        }

        $query = $role->permissions()->withPivot('granted_by', 'granted_at', 'created_at')
                      ->with('grantedBy:id,name,email');

        // Recherche par nom de permission
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $permissions = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'role' => ['id' => $role->id, 'name' => $role->name],
            'permissions' => $permissions->items(),
            'pagination' => [
                'total' => $permissions->total(),
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
            ]
        ], 200);
    }

    /**
     * Liste les rôles ayant une permission spécifique, avec recherche.
     */
    public function rolesByPermission(Request $request, $permissionId)
    {
        $permission = Permission::find($permissionId);
        if (!$permission) {
            return response()->json(['message' => 'Permission non trouvée.'], 404);
        }

        $query = $permission->roles()->withPivot('granted_by', 'granted_at', 'created_at')
                     ->with('grantedBy:id,name,email');

        // Recherche par nom de rôle
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'permission' => ['id' => $permission->id, 'name' => $permission->name],
            'roles' => $roles->items(),
            'pagination' => [
                'total' => $roles->total(),
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
            ]
        ], 200);
    }
}