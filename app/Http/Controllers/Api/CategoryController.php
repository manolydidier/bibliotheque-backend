<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /* ===============================================================
       🔹 INDEX — Liste simple
    =============================================================== */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();
        return response()->json($categories);
    }

    /* ===============================================================
       🔹 INDEX AVANCÉ — Recherche + pagination
    =============================================================== */
    public function index2(Request $request): JsonResponse
{
    $q = trim((string) $request->get('q', ''));
    $perPage = (int) $request->get('per_page', 24);
    $perPage = $perPage > 0 ? $perPage : 10;

    $query = Category::query()
        ->when($q !== '', function ($r) use ($q) {
            $r->where('name', 'like', "%{$q}%")
              ->orWhere('description', 'like', "%{$q}%");
        })
        ->orderBy('name', 'asc')
        ->orderBy('id', 'asc');

    $categories = $query->paginate($perPage);

    return response()->json([
        'status' => 'success',
        'code'   => 200,
        'message'=> 'Liste paginée des catégories récupérée avec succès.',
        'data'   => $categories->items(),
        'meta'   => [
            'current_page' => $categories->currentPage(),
            'total'        => $categories->total(),
            'last_page'    => $categories->lastPage(),
            'per_page'     => $categories->perPage(),
        ],
    ]);
}


    /* ===============================================================
       🔹 STORE — Créer une catégorie
    =============================================================== */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:categories,name',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:100',
        ], [
            'name.required' => 'Le nom de la catégorie est requis.',
            'name.unique' => 'Le nom existe déjà.',
            'name.max' => 'Le nom ne doit pas dépasser 50 caractères.',
            'color.max' => 'La couleur n’est pas valide.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 422,
                'message' => 'Erreur de validation des champs.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $validated['created_by'] = Auth::id();

            $category = Category::create($validated);

            return response()->json([
                'status'  => 'success',
                'code'    => 201,
                'message' => 'Catégorie créée avec succès.',
                'data'    => $category,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Erreur interne du serveur.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ===============================================================
       🔹 SHOW — Détail d'une catégorie
    =============================================================== */
    public function show(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    /* ===============================================================
       🔹 UPDATE — Modifier une catégorie
    =============================================================== */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('categories')->ignore($category->id),
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:100',
        ], [
            'name.unique' => 'Ce nom est déjà utilisé par une autre catégorie.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 422,
                'message' => 'Erreur de validation des champs.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $validated['updated_by'] = Auth::id();

            $category->update($validated);

            return response()->json([
                'status'  => 'success',
                'code'    => 200,
                'message' => 'Catégorie mise à jour avec succès.',
                'data'    => $category,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Erreur interne du serveur.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ===============================================================
       🔹 DESTROY — Soft delete (corbeille)
    =============================================================== */
    public function destroy(string $id): JsonResponse
    {
        Category::findOrFail($id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Catégorie envoyée dans la corbeille avec succès.',
        ]);
    }

    /* ===============================================================
       🔹 TRASHED — Lister les catégories supprimées
    =============================================================== */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = Category::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->select('categories.*');

        // Optionnel : filtrage
        if ($search = $request->get('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $trashed = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'message'=> 'Catégories supprimées récupérées avec succès.',
            'data'   => $trashed->items(),
            'meta'   => [
                'current_page' => $trashed->currentPage(),
                'total'        => $trashed->total(),
                'per_page'     => $trashed->perPage(),
            ],
        ]);
    }

    /* ===============================================================
       🔹 RESTORE — Restaurer une catégorie supprimée
    =============================================================== */
    public function restore(string $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->restore();

        return response()->json([
            'status'  => 'success',
            'message' => 'Catégorie restaurée avec succès.',
        ]);
    }

    /* ===============================================================
       🔹 FORCE DELETE — Supprimer définitivement
    =============================================================== */
 public function forceDelete(string $id): JsonResponse
{
    $category = Category::onlyTrashed()->find($id);

    if (!$category) {
        return response()->json([
            'status' => 'error',
            'code'   => 404,
            'message'=> 'Catégorie non trouvée dans la corbeille.',
        ], 404);
    }

    $category->forceDelete();

    return response()->json([
        'status'  => 'success',
        'code'    => 200,
        'message' => 'Catégorie supprimée définitivement.',
    ], 200);
}

}
