<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Regenerasi session untuk keamanan dari Session Fixation Attack
            $request->session()->regenerate();

            $user = Auth::user();

            return match ($user->role) {
                'super_admin', 'superadmin' => redirect()->route('superadmin.dashboard'),
                'admin'                     => redirect()->route('admin.dashboard'),
                'seller'                    => redirect()->route('seller.dashboard'),
                'cs', 'customer_service'    => redirect()->route('cs.dashboard'),
                default                     => redirect()->intended(route('home')),
            };
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }
}
