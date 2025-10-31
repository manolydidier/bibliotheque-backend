<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleReactionRequest;
use App\Models\Reaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReactionController extends Controller
{
    /**
     * Toggle / add / remove reaction
     * POST /api/reactions/toggle
     */
    public function toggle(ToggleReactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type'); // like | favorite
        $action = $request->input('action', 'toggle');
        $reactableType = $request->input('reactable_type');
        $reactableId = $request->input('reactable_id');

        // Normalize reactable_type (optional) - allow short name 'Article' or full App\Models\Article
        if (!str_contains($reactableType, '\\')) {
            $reactableType = "\\App\\Models\\" . trim($reactableType, '\\');
        }

        // Begin transaction to avoid race conditions
        return DB::transaction(function () use ($user, $type, $action, $reactableType, $reactableId) {
            $existing = Reaction::where([
                ['user_id', $user->id],
                ['reactable_type', $reactableType],
                ['reactable_id', $reactableId],
                ['type', $type],
            ])->first();

            if ($action === 'add') {
                if (!$existing) {
                    Reaction::create([
                        'user_id' => $user->id,
                        'reactable_type' => $reactableType,
                        'reactable_id' => $reactableId,
                        'type' => $type,
                    ]);
                    $status = 'added';
                } else {
                    $status = 'exists';
                }
            } elseif ($action === 'remove') {
                if ($existing) {
                    $existing->delete();
                    $status = 'removed';
                } else {
                    $status = 'not_found';
                }
            } else { // toggle
                if ($existing) {
                    $existing->delete();
                    $status = 'removed';
                } else {
                    Reaction::create([
                        'user_id' => $user->id,
                        'reactable_type' => $reactableType,
                        'reactable_id' => $reactableId,
                        'type' => $type,
                    ]);
                    $status = 'added';
                }
            }

            // retour des nouveaux comptes (performant via withCount style)
            $likesCount = Reaction::where('reactable_type', $reactableType)
                ->where('reactable_id', $reactableId)
                ->where('type', 'like')->count();

            $favoritesCount = Reaction::where('reactable_type', $reactableType)
                ->where('reactable_id', $reactableId)
                ->where('type', 'favorite')->count();

            return response()->json([
                'status' => $status,
                'counts' => [
                    'likes' => $likesCount,
                    'favorites' => $favoritesCount,
                ],
                'user' => [
                    'liked' => Reaction::where('reactable_type', $reactableType)->where('reactable_id', $reactableId)->where('type', 'like')->where('user_id', $user->id)->exists(),
                    'favorited' => Reaction::where('reactable_type', $reactableType)->where('reactable_id', $reactableId)->where('type', 'favorite')->where('user_id', $user->id)->exists(),
                ],
            ]);
        });
    }

    /**
     * Get reactions counts for a batch of reactable ids (useful to fill Grid)
     * GET /api/reactions/counts?reactable_type=Article&ids[]=1&ids[]=2
     */
    public function counts()
    {
        $reactableType = request('reactable_type');
        $ids = request('ids', []);
        if (!str_contains($reactableType, '\\')) {
            $reactableType = "\\App\\Models\\" . trim($reactableType, '\\');
        }
        $rows = Reaction::select('reactable_id', 'type', DB::raw('COUNT(*) as cnt'))
            ->where('reactable_type', $reactableType)
            ->whereIn('reactable_id', $ids)
            ->groupBy('reactable_id', 'type')
            ->get();

        // transform to [id => ['likes' => x, 'favorites' => y]]
        $map = [];
        foreach ($rows as $r) {
            $id = $r->reactable_id;
            $map[$id] = $map[$id] ?? ['likes' => 0, 'favorites' => 0];
            if ($r->type === 'like') $map[$id]['likes'] = $r->cnt;
            if ($r->type === 'favorite') $map[$id]['favorites'] = $r->cnt;
        }

        return response()->json($map);
    }

    /**
     * Get current user's reactions on a list of items
     * GET /api/reactions/me?reactable_type=Article&ids[]=1&ids[]=2
     */
    public function me()
    {
        $user = request()->user();
        $reactableType = request('reactable_type');
        $ids = request('ids', []);
        if (!str_contains($reactableType, '\\')) {
            $reactableType = "\\App\\Models\\" . trim($reactableType, '\\');
        }

        $rows = Reaction::where('user_id', $user->id)
            ->where('reactable_type', $reactableType)
            ->whereIn('reactable_id', $ids)
            ->get();

        // Map: id => ['liked' => bool, 'favorited' => bool]
        $map = [];
        foreach ($rows as $r) {
            $map[$r->reactable_id] = $map[$r->reactable_id] ?? ['liked' => false, 'favorited' => false];
            if ($r->type === 'like') $map[$r->reactable_id]['liked'] = true;
            if ($r->type === 'favorite') $map[$r->reactable_id]['favorited'] = true;
        }

        return response()->json($map);
    }
}
