<?php
// app/Http/Controllers/Api/ActivityController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request, $userId)
    {
        abort_unless((int)$request->user()->id === (int)$userId, 403);

        $per  = min(max((int)$request->integer('per_page', 10), 1), 100);
        $from = $request->date('from');

        $q = Activity::query()
            ->where('recipient_id', $userId)
            ->when($from, fn($qq) => $qq->whereDate('created_at', '>=', $from))
            ->orderByDesc('id');

        return response()->json(
            $q->paginate($per)->toArray()
        );
    }

    public function count(Request $request, $userId)
    {
        abort_unless((int)$request->user()->id === (int)$userId, 403);
        $since = $request->date('since'); // ISO8601 optionnel
        $q = Activity::query()->where('recipient_id', $userId);
        if ($since) $q->where('created_at', '>', $since);
        return ['count' => $q->count()];
    }
}
