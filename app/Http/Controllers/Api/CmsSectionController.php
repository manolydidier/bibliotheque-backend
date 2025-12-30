<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsSection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class CmsSectionController extends Controller
{
    /**
     * ✅ TEMP: forcer un tenant unique (1)
     * Plus tard tu pourras remettre: user()->tenant_id ou header X-Tenant-ID.
     */
    private function tenantId(): int
    {
        return 1;
    }

    private function rules(Request $request, ?CmsSection $section = null): array
    {
        $id = $section?->id;
        $tenantId = $this->tenantId();

        return [
            'category' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],

            // slugs
            'template' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'section'  => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'locale'   => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z0-9_-]+$/'],

            // content
            'gjs_project' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'css'  => ['nullable', 'string'],
            'js'   => ['nullable', 'string'],

            'status' => ['nullable', 'string', Rule::in(CmsSection::allowedStatuses())],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],

            'version' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'meta' => ['nullable', 'array'],

            // ✅ slot unique PAR TENANT (même si tenant=1 pour l’instant)
            'slot_unique' => [
                function ($attr, $value, $fail) use ($request, $id, $tenantId) {
                    $tpl = (string) $request->input('template', '');
                    $sec = (string) $request->input('section', '');
                    $loc = (string) $request->input('locale', 'fr');

                    if (!$tpl || !$sec || !$loc) return;

                    $exists = CmsSection::query()
                        ->where('tenant_id', $tenantId)
                        ->where('template', $tpl)
                        ->where('section', $sec)
                        ->where('locale', $loc)
                        ->when($id, fn($q) => $q->where('id', '!=', $id))
                        ->exists();

                    if ($exists) {
                        $fail("Ce slot existe déjà (template+section+locale) pour ce tenant.");
                    }
                }
            ],
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->tenantId();

        $q = CmsSection::query()->where('tenant_id', $tenantId);

        // filtres
        if ($request->filled('status'))   $q->where('status', $request->string('status'));
        if ($request->filled('category')) $q->where('category', $request->string('category'));
        if ($request->filled('template')) $q->where('template', $request->string('template'));
        if ($request->filled('section'))  $q->where('section', $request->string('section'));
        if ($request->filled('locale'))   $q->where('locale', $request->string('locale'));

        // search
        if ($request->filled('q')) {
            $needle = '%' . $request->string('q') . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('title', 'like', $needle)
                    ->orWhere('category', 'like', $needle)
                    ->orWhere('template', 'like', $needle)
                    ->orWhere('section', 'like', $needle);
            });
        }

        $q->orderBy('sort_order')->orderByDesc('updated_at');

        if ((int) $request->query('all', 0) === 1) {
            return response()->json(['data' => $q->get()]);
        }

        $perPage = max(1, min(200, (int) $request->query('per_page', 20)));
        return response()->json($q->paginate($perPage));
    }

    public function show(Request $request, CmsSection $cmsSection)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cmsSection->tenant_id === $tenantId, 404);

        return response()->json(['data' => $cmsSection]);
    }
   public function showpublic(Request $request, CmsSection $cmsSection)
    {
        // ✅ on ne sert que le contenu publié
        if (strtolower((string) $cmsSection->status) !== 'published') {
            abort(404);
        }

        // ✅ optionnel: vérifier le title=Mission si tu veux être strict
        $title = $request->query('title');
        if ($title && strcasecmp((string) $cmsSection->title, (string) $title) !== 0) {
            abort(404);
        }

        return response()->json([
            'id' => $cmsSection->id,
            'title' => $cmsSection->title,
            'status' => $cmsSection->status,
            'project' => $cmsSection->project ?? null, // si tu stockes le JSON Grapes
            'html' => $cmsSection->html ?? '',
            'css' => $cmsSection->css ?? '',
            'js' => $cmsSection->js ?? '',
            'updated_at' => optional($cmsSection->updated_at)->toISOString(),
        ]);
    }
    /**
     * GET /api/cms-sections/slot?template=...&section=...&locale=fr&status=published|draft|pending
     */
    public function slot(Request $request)
    {
        $tenantId = $this->tenantId();

        $request->validate([
            'template' => ['required', 'string', 'max:80'],
            'section'  => ['required', 'string', 'max:80'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'status'   => ['nullable', 'string', Rule::in(CmsSection::allowedStatuses())],
        ]);

        $tpl = (string) $request->input('template');
        $sec = (string) $request->input('section');
        $loc = (string) $request->input('locale', 'fr');
        $status = $request->input('status'); // null => le plus récent

        $q = CmsSection::query()
            ->where('tenant_id', $tenantId)
            ->where('template', $tpl)
            ->where('section', $sec)
            ->where('locale', $loc);

        if ($status) $q->where('status', $status);

        $item = $q->orderByDesc('updated_at')->first();

        return response()->json(['data' => $item]);
    }

    public function store(Request $request)
    {
        // champ virtuel pour déclencher la validation slot_unique
        $request->merge(['slot_unique' => '1']);

        $data = $request->validate($this->rules($request, null));

        $cms = new CmsSection();
        $cms->fill($data);

        // ✅ FORCER tenant_id = 1
        $cms->tenant_id = $this->tenantId();

        $cms->status = $cms->status ?: CmsSection::STATUS_DRAFT;
        $cms->locale = $cms->locale ?: 'fr';

        // si pas d'auth, ça restera null (OK si ta colonne accepte null)
        $cms->created_by = optional($request->user())->id;
        $cms->updated_by = optional($request->user())->id;

        try {
            $cms->save();
        } catch (QueryException $e) {
            // si tu as un index unique côté DB (tenant_id, template, section, locale)
            if (str_contains($e->getMessage(), 'cms_sections_slot_unique')) {
                return response()->json([
                    'message' => 'Slot déjà existant (template+section+locale).',
                    'errors' => ['template' => ['Ce slot existe déjà.']],
                ], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $cms], 201);
    }

    public function update(Request $request, CmsSection $cmsSection)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cmsSection->tenant_id === $tenantId, 404);

        $request->merge(['slot_unique' => '1']);
        $data = $request->validate($this->rules($request, $cmsSection));

        $cmsSection->fill($data);

        // ✅ FORCER tenant_id = 1 (verrou)
        $cmsSection->tenant_id = $tenantId;

        $cmsSection->updated_by = optional($request->user())->id;

        if ($request->filled('published_at')) {
            $cmsSection->status = CmsSection::STATUS_PUBLISHED;
        }

        try {
            $cmsSection->save();
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'cms_sections_slot_unique')) {
                return response()->json([
                    'message' => 'Slot déjà existant (template+section+locale).',
                    'errors' => ['template' => ['Ce slot existe déjà.']],
                ], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $cmsSection]);
    }

    public function destroy(Request $request, CmsSection $cmsSection)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cmsSection->tenant_id === $tenantId, 404);

        $cmsSection->delete();
        return response()->json(['ok' => true]);
    }

    public function publish(Request $request, CmsSection $cmsSection)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cmsSection->tenant_id === $tenantId, 404);

        $cmsSection->publish(now());
        $cmsSection->updated_by = optional($request->user())->id;
        $cmsSection->save();

        return response()->json(['data' => $cmsSection]);
    }

    public function unpublish(Request $request, CmsSection $cmsSection)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cmsSection->tenant_id === $tenantId, 404);

        $cmsSection->unpublish();
        $cmsSection->updated_by = optional($request->user())->id;
        $cmsSection->save();

        return response()->json(['data' => $cmsSection]);
    }

    public function schedule(Request $request, CmsSection $cmsSection)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cmsSection->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $cmsSection->schedule(new \DateTime($data['scheduled_at']));
        $cmsSection->updated_by = optional($request->user())->id;
        $cmsSection->save();

        return response()->json(['data' => $cmsSection]);
    }
}
