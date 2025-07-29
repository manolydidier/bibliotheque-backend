<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            ['name' => 'users.create', 'description' => 'Créer des utilisateurs', 'resource' => 'users', 'action' => 'create'],
            ['name' => 'users.read', 'description' => 'Consulter les utilisateurs', 'resource' => 'users', 'action' => 'read'],
            ['name' => 'users.update', 'description' => 'Modifier les utilisateurs', 'resource' => 'users', 'action' => 'update'],
            ['name' => 'users.delete', 'description' => 'Supprimer les utilisateurs', 'resource' => 'users', 'action' => 'delete'],

            // Books
            ['name' => 'books.create', 'description' => 'Ajouter des livres', 'resource' => 'books', 'action' => 'create'],
            ['name' => 'books.read', 'description' => 'Consulter les livres', 'resource' => 'books', 'action' => 'read'],
            ['name' => 'books.update', 'description' => 'Modifier les livres', 'resource' => 'books', 'action' => 'update'],
            ['name' => 'books.delete', 'description' => 'Supprimer les livres', 'resource' => 'books', 'action' => 'delete'],

            // Loans
            ['name' => 'loans.create', 'description' => 'Créer des emprunts', 'resource' => 'loans', 'action' => 'create'],
            ['name' => 'loans.read', 'description' => 'Consulter les emprunts', 'resource' => 'loans', 'action' => 'read'],
            ['name' => 'loans.update', 'description' => 'Modifier les emprunts', 'resource' => 'loans', 'action' => 'update'],
            ['name' => 'loans.delete', 'description' => 'Annuler des emprunts', 'resource' => 'loans', 'action' => 'delete'],

            // Roles & Permissions
            ['name' => 'roles.manage', 'description' => 'Gérer les rôles', 'resource' => 'roles', 'action' => 'manage'],
            ['name' => 'permissions.manage', 'description' => 'Gérer les permissions', 'resource' => 'permissions', 'action' => 'manage'],

            // Extras
            ['name' => 'reports.generate', 'description' => 'Générer des rapports', 'resource' => 'reports', 'action' => 'create'],
            ['name' => 'system.config', 'description' => 'Configuration système', 'resource' => 'system', 'action' => 'update'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->insert([
                'name' => $p['name'],
                'description' => $p['description'],
                'resource' => $p['resource'],
                'action' => $p['action'],
                'created_at' => now(),
            ]);
        }
    }
}