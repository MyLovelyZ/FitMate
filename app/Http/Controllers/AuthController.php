<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login dan registrasi ditangani komponen Livewire di `resources/views/pages`.
 * Yang tersisa di sini hanya logout, karena itu aksi POST biasa tanpa halaman.
 */
class AuthController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
