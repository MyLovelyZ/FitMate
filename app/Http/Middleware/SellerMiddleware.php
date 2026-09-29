<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SellerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && in_array(Auth::user()->role, ['seller', 'penjual', 'admin'])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Akses ditolak. Khusus Penjual/Seller.'], 403);
        }

        return redirect('/')->with('error', 'Anda tidak memiliki akses ke halaman Seller.');
    }
}