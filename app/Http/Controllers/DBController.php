<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DBController extends Controller
{
    public function getTables()
    {
        $database = env('DB_DATABASE');

        // Liste des tables à exclure
        $excludedTables = [
            'migrations',
            'personal_access_tokens',
            'failed_jobs' // si tu veux exclure les sessions, ajoute ici
        ];

        $tables = DB::table('information_schema.tables')
            ->select('TABLE_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->whereNotIn('TABLE_NAME', $excludedTables)
            ->pluck('TABLE_NAME');

        return response()->json([
            'success' => true,
            'database' => $database,
            'tables' => $tables
        ]);
    }
}
