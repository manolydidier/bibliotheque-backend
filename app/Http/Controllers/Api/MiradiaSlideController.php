<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MiradiaSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MiradiaSlideController extends Controller
{
    /**
     * GET /api/miradia-slides
     * ?page=&per_page=&all=1
     *  - all=1 : tous les slides (back-office)
     *  - sinon : seulement is_active = true (front)
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 12);
        $perPage = max(1, min($perPage, 100));
        $all     = $request->boolean('all', false);

        $query = MiradiaSlide::query();

        if (!$all) {
            $query->where('is_active', true);
        }

        $slides = $query
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json($slides);
    }

    /**
     * GET /api/miradia-slides/{slide}
     * Récupère un slide (pour le formulaire d’édition).
     */
    public function show(MiradiaSlide $slide)
    {
        return response()->json($slide);
    }

    /**
     * POST /api/miradia-slides
     * Crée un slide.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'stat_label'  => 'nullable|string|max:255',
            'tag'         => 'nullable|string|max:50',
            'icon'        => 'nullable|string|max:50',
            'color'       => 'nullable|string|max:20',
            'position'    => 'nullable|integer|min:1',
            'is_active'   => 'nullable|boolean',
            'image'       => 'nullable|image|max:4096', // 4 Mo
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('miradia-slides', 'public');
        }

        $slide = MiradiaSlide::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'stat_label'  => $data['stat_label'] ?? null,
            'tag'         => $data['tag'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'color'       => $data['color'] ?? '#0ea5e9',
            'position'    => $data['position'] ?? 1,
            'is_active'   => $data['is_active'] ?? true,
            'image_path'  => $imagePath,
        ]);

        return response()->json($slide, 201);
    }

    /**
     * PUT/PATCH /api/miradia-slides/{slide}
     * Met à jour un slide.
     */
    public function update(Request $request, MiradiaSlide $slide)
    {
        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'stat_label'  => 'nullable|string|max:255',
            'tag'         => 'nullable|string|max:50',
            'icon'        => 'nullable|string|max:50',
            'color'       => 'nullable|string|max:20',
            'position'    => 'nullable|integer|min:1',
            'is_active'   => 'nullable|boolean',
            'image'       => 'nullable|image|max:4096',
        ]);

        foreach (['title','description','stat_label','tag','icon','color','position','is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $slide->{$field} = $data[$field];
            }
        }

        if ($request->hasFile('image')) {
            if ($slide->image_path && Storage::disk('public')->exists($slide->image_path)) {
                Storage::disk('public')->delete($slide->image_path);
            }
            $slide->image_path = $request->file('image')->store('miradia-slides', 'public');
        }

        $slide->save();

        return response()->json($slide);
    }

    /**
     * DELETE /api/miradia-slides/{slide}
     * Supprime un slide.
     */
    public function destroy(MiradiaSlide $slide)
    {
        if ($slide->image_path && Storage::disk('public')->exists($slide->image_path)) {
            Storage::disk('public')->delete($slide->image_path);
        }

        $slide->delete();

        return response()->json([
            'message' => 'Slide supprimé',
        ]);
    }
}
