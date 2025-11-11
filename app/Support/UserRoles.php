<?php
// app/Support/UserRoles.php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRoles
{
    public static function isAdmin($user): bool
    {
        return self::hasAnyRole($user, ['admin','administrator','super admin','superadmin','super-administrator']);
    }

    public static function isModerator($user): bool
    {
        // adapte selon ta nomenclature/permissions
        if (self::hasAnyRole($user, ['moderator','mod','editor'])) return true;

        if (Schema::hasTable('user_permissions')) {
            return DB::table('user_permissions as up')
                ->join('permissions as p', 'p.id', '=', 'up.permission_id')
                ->where('up.user_id', $user->id)
                ->whereIn('p.action', ['comments.update','comments.approve','comments.moderate'])
                ->exists();
        }
        return false;
    }

    private static function hasAnyRole($user, array $names): bool
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('user_roles')) return false;
        return DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $user->id)
            ->whereIn(DB::raw('LOWER(r.name)'), array_map('strtolower', $names))
            ->exists();
    }
}
