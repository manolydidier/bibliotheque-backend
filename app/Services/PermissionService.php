<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionService
{
    public function userHasAny(int $userId, array $needles): bool
    {
        if ($userId <= 0 || empty($needles)) return false;
        $needles = array_map(fn($x) => mb_strtolower((string) $x), $needles);
        $hasSlugRole = Schema::hasColumn('roles','slug');
        $hasSlugPerm = Schema::hasColumn('permissions','slug');

        // Rôles
        $roleQ = DB::table('roles as r')
            ->join('user_roles as ur','ur.role_id','=','r.id')
            ->where('ur.user_id', $userId)
            ->where(function($q) use ($needles,$hasSlugRole){
                $q->whereIn(DB::raw('LOWER(r.name)'), $needles);
                if ($hasSlugRole) $q->orWhereIn(DB::raw('LOWER(r.slug)'), $needles);
            })
            ->limit(1);
        if ($roleQ->exists()) return true;

        // Permissions
        $permQ = DB::table('permissions as p')
            ->join('role_permissions as rp','rp.permission_id','=','p.id')
            ->join('roles as r','r.id','=','rp.role_id')
            ->join('user_roles as ur','ur.role_id','=','r.id')
            ->where('ur.user_id', $userId)
            ->where(function($q) use ($needles,$hasSlugPerm){
                $q->whereIn(DB::raw('LOWER(p.name)'), $needles);
                if ($hasSlugPerm) $q->orWhereIn(DB::raw('LOWER(p.slug)'), $needles);
            })
            ->limit(1);
        return $permQ->exists();
    }
}
