<?php
// App\Http\Controllers\PlatformUpdatesController.php

namespace App\Http\Controllers;

use App\Services\GitHubCommitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlatformUpdatesController extends Controller
{
    public function commits(GitHubCommitsService $gitHub): JsonResponse
    {
        try {
            $commits = $gitHub->getLatestCommits();

            return response()->json([
                'frontend' => $commits['frontend'],
                'backend'  => $commits['backend'],
            ]);
        } catch (\Throwable $e) {
            Log::error('GitHub commits error (commits endpoint)', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Impossible de récupérer les mises à jour GitHub.',
            ], 500);
        }
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'status'     => 'online',
            'detail'     => "Plateforme opérationnelle.",
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function updates(GitHubCommitsService $gitHub): JsonResponse
    {
        try {
            $commits = $gitHub->getLatestCommits();

            // ✅ On renvoie une structure simple + prête pour le front
            return response()->json([
                'frontend' => $commits['frontend'],
                'backend'  => $commits['backend'],
            ]);
        } catch (\Throwable $e) {
            

            // ❗ Option 1 : garder un 500 (comme actuellement)
            // return response()->json([
            //     'message' => 'Impossible de récupérer les mises à jour GitHub.',
            // ], 500);

            // ✅ Option 2 : NE PAS casser le front → on renvoie juste des listes vides
            return response()->json([
                'frontend' => [],
                'backend'  => [],
                'error'    => 'github_unreachable',
            ], 200);
        }
    }
}
