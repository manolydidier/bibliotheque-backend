<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    /**
     * Liste toutes les permissions avec pagination et recherche.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        // 🔍 Recherche globale
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('resource', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // 🎯 Filtres par champ
        if ($request->filled('resource')) {
            $query->where('resource', $request->resource);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // 📄 Pagination
        $permissions = $query->orderBy('name')->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $permissions,
        ]);
    }

    /**
     * Affiche une permission spécifique.
     */
    public function show($id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('permissions.not_found'),
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $permission,
        ]);
    }

    /**
     * Crée une nouvelle permission.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:100|unique:permissions,name',
                'description' => 'nullable|string|max:255',
                'resource' => 'required|string|max:50',
                'action' => 'required|string|max:50',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('validation.failed'),
                'errors' => $e->errors(),
            ], 422);
        }

        $permission = Permission::create($request->only(['name', 'description', 'resource', 'action']));

        return response()->json([
            'status' => 'success',
            'message' => __('permissions.created'),
            'data' => $permission,
        ], 201);
    }

    /**
     * Met à jour une permission.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('permissions.not_found'),
            ], 404);
        }

        try {
            $request->validate([
                'name' => 'required|string|max:100|unique:permissions,name,' . $id,
                'description' => 'nullable|string|max:255',
                'resource' => 'required|string|max:50',
                'action' => 'required|string|max:50',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('validation.failed'),
                'errors' => $e->errors(),
            ], 422);
        }

        $permission->update($request->only(['name', 'description', 'resource', 'action']));

        return response()->json([
            'status' => 'success',
            'message' => __('permissions.updated'),
            'data' => $permission,
        ]);
    }

    /**
     * Supprime une permission.
     */
    public function destroy($id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('permissions.not_found'),
            ], 404);
        }

        // 🔒 Optionnel : vérifier si la permission est utilisée
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => __('permissions.cannot_delete_assigned'),
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('permissions.deleted'),
        ]);
    }
}