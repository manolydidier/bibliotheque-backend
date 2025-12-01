<?php
// App/Http/Controllers/Api/SocieteController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Societe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SocieteController extends Controller
{
    public function index(Request $request)
{
    // status = all | active | inactive | archive
    $status = $request->query('status', 'all');

    $query = Societe::query();

    if ($status === 'active') {
        // garde ton scope si tu veux
        $query->active();
    } elseif ($status === 'inactive') {
        $query->where('is_active', false);
    } elseif ($status === 'archive') {
        // adapte si tu as un champ dédié pour l’archivage
        $query->where('statut', 'archive');
    }

    $societes = $query->orderBy('name')->get();

    return response()->json($societes);
}

    public function show(Societe $societe)
    {
        return response()->json($societe);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'website_url'   => 'nullable|string|max:255',
            'is_active'     => 'required|boolean',
            'responsable'   => 'nullable|string|max:255',
            'adresse'       => 'nullable|string|max:255',
            'ville'         => 'nullable|string|max:255',
            'pays'          => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'logo'          => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $filename = basename($path);
            $data['logo_url'] = $filename;
        }

        $societe = Societe::create($data);

        return response()->json($societe, 201);
    }

    public function update(Request $request, Societe $societe)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'website_url'   => 'nullable|string|max:255',
            'is_active'     => 'required|boolean',
            'responsable'   => 'nullable|string|max:255',
            'adresse'       => 'nullable|string|max:255',
            'ville'         => 'nullable|string|max:255',
            'pays'          => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'logo'          => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            if ($societe->logo_url) {
                $oldPath = 'logos/'.$societe->logo_url;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $path = $request->file('logo')->store('logos', 'public');
            $filename = basename($path);
            $data['logo_url'] = $filename;
        }

        $societe->update($data);

        return response()->json($societe);
    }

    public function destroy(Societe $societe)
    {
        if ($societe->logo_url) {
            $path = 'logos/'.$societe->logo_url;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $societe->delete();

        return response()->json(['message' => 'Société supprimée.']);
    }
    // App\Http\Controllers\Api\SocieteController.php

       public function updateActive(Request $request, Societe $societe)
{
    // Pour être sûr que la route est bien appelée
    Log::info('updateActive appelé', [
        'societe_id'   => $societe->id,
        'payload'      => $request->all(),
        'is_active_db' => $societe->is_active,
    ]);

    try {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);
    } catch (ValidationException $e) {
        Log::warning('Validation échouée dans updateActive', [
            'errors'  => $e->errors(),
            'payload' => $request->all(),
        ]);

        return response()->json([
            'message' => 'Erreur de validation sur is_active',
            'errors'  => $e->errors(),
            'payload' => $request->all(),
        ], 422);
    }

    try {
        $before = $societe->getOriginal('is_active');

        $societe->is_active = $data['is_active'];
        $societe->save();

        $after = $societe->is_active;

        Log::info('updateActive: statut mis à jour', [
            'societe_id'     => $societe->id,
            'is_active_avant'=> $before,
            'is_active_apres'=> $after,
        ]);

        return response()->json([
            'message'  => 'Statut mis à jour avec succès.',
            'debug'    => [
                'input'           => $request->all(),
                'is_active_avant' => $before,
                'is_active_apres' => $after,
                'type_reçu'       => gettype($data['is_active']),
            ],
            'societe' => $societe->fresh(), // renvoie l’objet à jour (avec statut si appends)
        ]);
    } catch (\Throwable $e) {
        Log::error('Erreur dans updateActive', [
            'societe_id' => $societe->id,
            'exception'  => $e->getMessage(),
            'trace'      => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message'   => 'Erreur lors de la mise à jour du statut.',
            'exception' => $e->getMessage(),
        ], 500);
    }

}
}