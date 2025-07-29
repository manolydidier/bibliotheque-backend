<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserRolePermissionController extends Controller
{
     public function show($id)
    {
        $user = User::with('roles.permissions')->findOrFail($id);

        return response()->json([
            'users' => $user->username,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->permissions()->pluck('name'),
        ]);
    }
    
     public function index()
    {
        $users = User::with('roles.permissions')->get();

        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->permissions()->pluck('name')->toArray(),
            ];
        });

        return response()->json($data);
    }
    

}
