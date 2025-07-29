<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'Super Admin' => 'ALL',
            'Admin' => [
                'users.create', 'users.read', 'users.update', 'users.delete',
                'books.create', 'books.read', 'books.update', 'books.delete',
                'loans.create', 'loans.read', 'loans.update', 'loans.delete',
                'roles.manage', 'permissions.manage',
                'reports.generate', 'system.config',
            ],
            'Bibliothécaire' => [
                'books.create', 'books.read', 'books.update',
                'loans.create', 'loans.read', 'loans.update',
                'reports.generate',
            ],
            'Membre Premium' => [
                'books.read', 'loans.create', 'loans.read',
            ],
            'Membre' => [
                'books.read', 'loans.read',
            ],
        ];

        $roles = DB::table('roles')->pluck('id', 'name')->toArray();
        $permissions = DB::table('permissions')->pluck('id', 'name')->toArray();

        foreach ($rolePermissions as $roleName => $permNames) {
            $roleId = $roles[$roleName] ?? null;
            if (!$roleId) {
                echo "⚠️ Rôle introuvable : $roleName\n";
                continue;
            }

            $toInsert = [];

            if ($permNames === 'ALL') {
                foreach ($permissions as $permId) {
                    $toInsert[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                        'granted_at' => now(),
                        'granted_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            } else {
                foreach ($permNames as $permName) {
                    $permId = $permissions[$permName] ?? null;
                    if (!$permId) {
                        echo "❌ Permission introuvable : $permName\n";
                        continue;
                    }

                    $toInsert[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                        'granted_at' => now(),
                        'granted_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::table('role_permissions')->insert($toInsert);
        }
    }
}