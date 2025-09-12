<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TagController extends Controller
{  


    /**
     * Affiche la liste de tous les tags.
     */
    public function index(): JsonResponse
    {
        $tags = Tag::orderBy('name')->get();
        return response()->json($tags);
    }



     /**
     * Liste des tags avec recherche + pagination.
     * GET /api/tags?q=...&page=1&per_page=20
     */
    public function index2(Request $request): JsonResponse
    {
         $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 5);
        $perPage = $perPage > 0 ? $perPage : 10;

        $query = Tag::query()
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
            ->select('tags.*')
            ->distinct();

        $tags = $query->paginate($perPage);

        return response()->json($tags);
    }

    /**
     * Crée un nouveau tag.
     */
    public function store(Request $request): JsonResponse
    {
         
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:tags,name',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',         
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag = Tag::create($validator->validated());

        return response()->json($tag, 201);
    }

    /**
     * Affiche un tag spécifique.
     */
    public function show(string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        return response()->json($tag);
    }

    /**
     * Met à jour un tag existant.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('tags')->ignore($tag->id),
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag->update($validator->validated());

        return response()->json($tag);
    }

    /**
     * Supprime un tag.
     */
    public function destroy(string $id): JsonResponse
    {
        Tag::findOrFail($id)->delete();
        return response()->json(['message' => 'Tag supprimé avec succès.']);
    }
}

