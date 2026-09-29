<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerServiceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cek apakah user sudah login
        // 2. Izinkan jika role user adalah 'cs' ATAU 'admin'
        if (Auth::check() && in_array(Auth::user()->role, ['cs', 'customer_service', 'admin'])) {
            return $next($request);
        }

        // Response jika request API / JSON
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Akses ditolak. Khusus Customer Service.'], 403);
        }

        // Redirect jika user biasa mencoba mengakses halaman CS
        return redirect('/')->with('error', 'Anda tidak memiliki akses ke halaman Customer Service.');
    }
}