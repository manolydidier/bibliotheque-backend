<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileCountersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileDownloadController extends Controller
{
    public function __construct(private FileCountersService $counters) {}

    /**
     * POST /api/media/{media}/download
     * Body (optionnel): { tenant_id?: int }
     */
    public function store(Request $request, int $media): JsonResponse
    {
        $tenantId = $request->integer('tenant_id');
        $ua = (string) $request->header('User-Agent', '');
        $ip = (string) $request->ip();
        $dedupe = $ip . '|' . $ua . '|media:' . $media;

        $counted = $this->counters->incrementDownload(
            fileId: $media,
            delta: 1,
            dedupeKey: $dedupe,
            ttlSeconds: 600,       // 10 minutes anti-doublon
            tenantId: $tenantId ?: null
        );

        return response()->json(['ok' => true, 'counted' => (bool) $counted]);
    }
}
