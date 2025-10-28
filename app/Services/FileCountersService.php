<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FileCountersService
{
    /**
     * Incrémente l'agrégat de téléchargements (table file_downloads)
     *
     * @param  int         $fileId
     * @param  int         $delta
     * @param  string|null $dedupeKey   Clé de dédup (IP|UA…), sinon pas de dédup
     * @param  int         $ttlSeconds  TTL de la dédup (ex: 600 = 10 min)
     * @param  int|null    $tenantId
     * @return bool        true si incrément effectué; false si ignoré (dédup)
     */
    public function incrementDownload(
        int $fileId,
        int $delta = 1,
        ?string $dedupeKey = null,
        int $ttlSeconds = 600,
        ?int $tenantId = null
    ): bool {
        // Déduplication soft
        if ($dedupeKey) {
            $cacheKey = 'downloaded:file:' . $fileId . ':' . sha1($dedupeKey);
            if (!Cache::add($cacheKey, 1, $ttlSeconds)) {
                return false; // déjà compté récemment
            }
        }

        $today = Carbon::today()->toDateString();

        DB::transaction(function () use ($fileId, $tenantId, $today, $delta) {
            $q = DB::table('file_downloads')
                ->where('file_id', $fileId)
                ->where('date', $today);

            is_null($tenantId) ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId);

            $updated = $q->increment('count', $delta);

            if ($updated === 0) {
                try {
                    DB::table('file_downloads')->insert([
                        'file_id'    => $fileId,
                        'tenant_id'  => $tenantId,
                        'date'       => $today,
                        'count'      => $delta,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Conflit d'unicité: quelqu'un a inséré entre-temps → re-incrément
                    $q->increment('count', $delta);
                }
            }
        });

        return true;
    }
}
