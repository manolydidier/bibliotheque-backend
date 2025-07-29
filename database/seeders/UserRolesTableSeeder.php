<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users')->pluck('id', 'username')->toArray();
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();

        $bindings = [
            ['username' => 'superadmin', 'role' => 'Super Admin'],
            ['username' => 'biblio', 'role' => 'Bibliothécaire'],
            ['username' => 'membre', 'role' => 'Membre'],
        ];

        foreach ($bindings as $bind) {
            $userId = $users[$bind['username']] ?? null;
            $roleId = $roles[$bind['role']] ?? null;

            if ($userId && $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned_at' => now(),
                    'assigned_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}