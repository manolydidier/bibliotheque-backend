<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserCreationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password_hash' => bcrypt($validated['password']),
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'is_active' => true,
            'email_verified' => false,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user->only(['id', 'username', 'email']),
        ]);
    }

}
