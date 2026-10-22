<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->role === 'superadmin') {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Akses ditolak. Khusus Super Admin.'], 403);
        }

        return redirect('/')->with('error', 'Anda tidak memiliki akses ke Super Admin.');
    }
}
