<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class CategoryController extends Controller
{
    /**
     * Affiche la liste de toutes les catégories.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();
        return response()->json($categories);
    }


    public function index2(Request $request): JsonResponse
    {
         $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 5);
        $perPage = $perPage > 0 ? $perPage : 10;

        $query = Category::query()
            ->when($q !== '', function ($r) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $r->where(function ($q2) use ($like) {
                    $q2->where('name', 'like', $like)
                       ->orWhere('description', 'like', $like);
                });
            })
            // tri stable & déterministe pour éviter les doublons entre pages
            ->orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->select('categories.*')
            ->distinct();

        $categories = $query->paginate($perPage);

        return response()->json($categories);
    }
 
    /**
     * Crée une nouvelle catégorie.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:categories,name',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create($validator->validated());

        return response()->json($category, 201);
    }
 
    /**
     * Affiche une catégorie spécifique.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    /**
     * Met à jour une catégorie existante.
     */
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
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update($validator->validated());

        return response()->json($category);
    }
 
    /**
     * Supprime une catégorie.
     */
    public function destroy(string $id): JsonResponse
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Categorie supprimée avec succès.']);
    }
}
