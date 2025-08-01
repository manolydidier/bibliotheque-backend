<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class CustomAuthenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        return null;
    }

    protected function unauthenticated($request, array $guards)
    {
        abort(response()->json([
            'message' => 'Non authentifié.',
        ], 401));
    }
}
