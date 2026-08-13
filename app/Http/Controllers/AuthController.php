<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function Register(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'nullable|string|max:20',
            'gender' => 'nullable|string|in:male,female',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8'
        ]);

        $user = \App\Models\User::create([
            'name' => $validate['name'],
            'email' => $validate['email'],
            'phone_number' => $validate['phone_number'] ?? null,
            'gender' => $validate['gender'] ?? null,
            'password' => $validate['password'],
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }
    public function Login(Request $request)
    {
        $credentials = $request->only('email', 'password');
    }
}
