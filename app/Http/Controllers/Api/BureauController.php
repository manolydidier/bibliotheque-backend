<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bureau;
use App\Models\Societe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BureauController extends Controller
{
    // GET /api/bureaux
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        $status   = $request->query('status', 'all'); // all | active | inactive

        $query = Bureau::with('societe')
            ->forTenant($tenantId)
            ->orderByDesc('is_primary')
            ->orderBy('city');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $bureaux = $query->get();

        return response()->json($bureaux);
    }

    // GET /api/societes/{societe}/bureaux
    public function indexBySociete(Request $request, Societe $societe)
    {
        $status = $request->query('status', 'active'); // active par défaut

        $query = $societe->bureaux()->orderByDesc('is_primary');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $bureaux = $query->get();

        return response()->json($bureaux);
    }

    // GET /api/bureaux/{bureau}
    public function show(Bureau $bureau)
    {
        $bureau->load('societe');

        return response()->json($bureau);
    }

    // POST /api/bureaux
    // (le front envoie societe_id dans le body, + image / image_url)
   public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'societe_id' => 'required|exists:societes,id',
            'name'       => 'required|string|max:255',
            'type'       => 'nullable|string|max:50',
            'city'       => 'required|string|max:255',   // 👈 OBLIGATOIRE
            'country'    => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:255',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'image_url'  => 'nullable|string|max:255',
            'image'      => 'nullable|image|max:2048',
            'is_primary' => 'boolean',
            'is_active'  => 'boolean',
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Certains champs sont invalides. Merci de vérifier le formulaire.',
            'errors'  => $e->errors(),
        ], 422);
    }

    $data = $validated;

    $data['country']    = $data['country'] ?? 'Madagascar';
    $data['is_active']  = array_key_exists('is_active', $data)
        ? (bool) $data['is_active']
        : true;
    $data['is_primary'] = !empty($data['is_primary']);

    if ($user = $request->user()) {
        if (isset($user->tenant_id)) {
            $data['tenant_id'] = $user->tenant_id;
        }
    }

    if ($request->hasFile('image')) {
        $path = $request->file('image')->store('bureaux', 'public');
        $data['image_url'] = basename($path);
    }

    if ($data['is_primary']) {
        Bureau::where('societe_id', $data['societe_id'])
            ->update(['is_primary' => false]);
    }

    $bureau = Bureau::create($data);

    return response()->json($bureau, 201);
}


    // PUT/PATCH /api/bureaux/{bureau}
    public function update(Request $request, Bureau $bureau)
{
    try {
        $validated = $request->validate([
            'societe_id' => 'sometimes|exists:societes,id',
            'name'       => 'sometimes|string|max:255',
            'type'       => 'sometimes|nullable|string|max:50',
            'city'       => 'sometimes|required|string|max:255', // 👈 si présent, pas vide
            'country'    => 'sometimes|nullable|string|max:255',
            'address'    => 'sometimes|nullable|string|max:255',
            'latitude'   => 'sometimes|nullable|numeric',
            'longitude'  => 'sometimes|nullable|numeric',
            'phone'      => 'sometimes|nullable|string|max:50',
            'email'      => 'sometimes|nullable|email|max:255',
            'image_url'  => 'sometimes|nullable|string|max:255',
            'image'      => 'sometimes|nullable|image|max:2048',
            'is_primary' => 'sometimes|boolean',
            'is_active'  => 'sometimes|boolean',
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Certains champs sont invalides. Merci de vérifier le formulaire.',
            'errors'  => $e->errors(),
        ], 422);
    }

    $data = $validated;

    if (array_key_exists('country', $data) && $data['country'] === null) {
        $data['country'] = 'Madagascar';
    }

    if (array_key_exists('is_active', $data)) {
        $data['is_active'] = (bool) $data['is_active'];
    }

    if (array_key_exists('is_primary', $data)) {
        $data['is_primary'] = (bool) $data['is_primary'];
    }

    if ($request->hasFile('image')) {
        if ($bureau->image_url) {
            $oldPath = 'bureaux/' . $bureau->image_url;
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('image')->store('bureaux', 'public');
        $data['image_url'] = basename($path);
    }

    if (isset($data['is_primary']) && $data['is_primary']) {
        $societeId = $data['societe_id'] ?? $bureau->societe_id;

        Bureau::where('societe_id', $societeId)
            ->where('id', '!=', $bureau->id)
            ->update(['is_primary' => false]);
    }

    $bureau->update($data);

    return response()->json($bureau);
}


    // DELETE /api/bureaux/{bureau}
    public function destroy(Bureau $bureau)
    {
        if ($bureau->image_url) {
            $path = 'bureaux/' . $bureau->image_url;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $bureau->delete();

        return response()->json(['message' => 'Bureau supprimé.']);
    }

    // POST /api/bureaux/{bureau}/active
    public function updateActive(Request $request, Bureau $bureau)
    {
        Log::info('Bureau updateActive appelé', [
            'bureau_id'   => $bureau->id,
            'payload'     => $request->all(),
            'is_active_db'=> $bureau->is_active,
        ]);

        try {
            $data = $request->validate([
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            Log::warning('Validation échouée dans Bureau::updateActive', [
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
            $before = $bureau->getOriginal('is_active');

            $bureau->is_active = $data['is_active'];
            $bureau->save();

            $after = $bureau->is_active;

            Log::info('Bureau updateActive: statut mis à jour', [
                'bureau_id'       => $bureau->id,
                'is_active_avant' => $before,
                'is_active_apres' => $after,
            ]);

            return response()->json([
                'message' => 'Statut mis à jour avec succès.',
                'debug'   => [
                    'input'           => $request->all(),
                    'is_active_avant' => $before,
                    'is_active_apres' => $after,
                    'type_reçu'       => gettype($data['is_active']),
                ],
                'bureau' => $bureau->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur dans Bureau::updateActive', [
                'bureau_id' => $bureau->id,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message'   => 'Erreur lors de la mise à jour du statut.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }
       /**
     * GET /api/public/bureaux-map
     * Route publique pour la carte : liste de bureaux avec coordonnées + infos société
     */
    public function publicMap()
    {
        // On charge tous les bureaux qui ont des coordonnées
        $bureaux = Bureau::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_active', true)
            ->with('societe') // ⚠️ plus de ->select('nom', ...) ici
            ->orderBy('city')
            ->get();

        $data = $bureaux->map(function (Bureau $bureau) {
            $societe = $bureau->societe;

            return [
                'id'        => $bureau->id,
                'name'      => $bureau->name ?? $bureau->nom ?? null,
                'city'      => $bureau->city,
                'country'   => $bureau->country ?? 'Madagascar',
                'address'   => $bureau->address,
                'phone'     => $bureau->phone,
                'email'     => $bureau->email,
                'latitude'  => $bureau->latitude,
                'longitude' => $bureau->longitude,
                'image_url' => $bureau->image_url
                    ?? ($bureau->image_path ? asset('storage/' . $bureau->image_path) : null),

                'societe'   => $societe ? [
                    'id'       => $societe->id,
                    // On s’adapte à ton schéma : nom / name
                    'nom'      => $societe->nom  ?? $societe->name  ?? null,
                    'sigle'    => $societe->sigle ?? $societe->slug ?? null,
                    'pays'     => $societe->pays ?? $societe->country ?? null,
                    'logo_url' => $societe->logo_url
                        ?? ($societe->logo_path ? asset('storage/' . $societe->logo_path) : null),
                ] : null,
            ];
        });

        return response()->json([
            'data' => $data->values(),
        ]);
    }

    /**
     * GET /api/public/bureaux/{bureau}
     * Fiche publique d’un bureau + autres bureaux de la même société
     */
    public function publicShow(Bureau $bureau)
    {
        $bureau->load('societe');
        $societe = $bureau->societe;

        // Payload bureau principal
        $bureauPayload = [
            'id'         => $bureau->id,
            'name'       => $bureau->name ?? $bureau->nom ?? null,
            'city'       => $bureau->city,
            'country'    => $bureau->country ?? 'Madagascar',
            'address'    => $bureau->address,
            'phone'      => $bureau->phone,
            'email'      => $bureau->email,
            'latitude'   => $bureau->latitude,
            'longitude'  => $bureau->longitude,
            'type'       => $bureau->type,
            'is_primary' => (bool) $bureau->is_primary,
             'image_url' => $bureau->image_url
                    ?? ($bureau->image_path ? asset('storage/' . $bureau->image_path) : null),
        ];

        // Payload société
        $societePayload = $societe ? [
            'id'       => $societe->id,
            'nom'      => $societe->nom  ?? $societe->name  ?? null,
            'sigle'    => $societe->sigle ?? $societe->slug ?? null,
            'pays'     => $societe->pays ?? $societe->country ?? null,
            'logo_url' => $societe->logo_url
                ?? ($societe->logo_path ? asset('storage/' . $societe->logo_path) : null),
        ] : null;

        // Autres bureaux de la même société
        $other = collect();
        if ($societe) {
            $other = $societe->bureaux()
                ->where('id', '!=', $bureau->id)
                ->where('is_active', true)
                ->orderBy('city')
                ->get()
                ->map(function (Bureau $b) {
                    return [
                        'id'       => $b->id,
                        'name'     => $b->name ?? $b->nom ?? null,
                        'city'     => $b->city,
                        'country'  => $b->country ?? 'Madagascar',
                        'address'  => $b->address,
                        'phone'    => $b->phone,
                        'email'    => $b->email,
                    ];
                });
        }

        return response()->json([
            'bureau'        => $bureauPayload,
            'societe'       => $societePayload,
            'other_bureaux' => $other->values(),
        ]);
    }
 
}
