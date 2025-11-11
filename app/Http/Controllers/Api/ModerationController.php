<?php
// app/Http/Controllers/Api/ModerationController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    private function ensureModerator(Request $request): void
    {
        $cc = app(CommentController::class); 
        abort_unless(app(CommentController::class)->userIsModerator($request->user()), 403);
    }

    public function pendingCount(Request $request)
    {
        $this->ensureModerator($request);
        $tenantId = $request->integer('tenant_id');
        $q = Comment::query()->where('status','pending');
        if ($tenantId) $q->where('tenant_id',$tenantId);
        return ['pending' => $q->count()];
    }

    public function pending(Request $request)
    {
        $this->ensureModerator($request);
        $per = min(max((int)$request->integer('per_page', 10), 1), 100);

        $q = Comment::query()
            ->with(['user:id,username,email,first_name,last_name,avatar_url,updated_at','article:id,slug,title'])
            ->where('status','pending')
            ->orderBy('created_at','desc');

        return response()->json($q->paginate($per)->toArray());
    }
}