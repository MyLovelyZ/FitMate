<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route berdasarkan `users.role`.
 *
 * Dipakai sebagai `role:seller` atau `role:admin,superadmin` di definisi route.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $allowed = array_filter(array_map(
            fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        ));

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Kamu tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
