<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel gate: only staff roles (super_admin, agent, editor) may enter.
 * Optional parameters narrow the allowed roles, e.g. "staff:super_admin,agent".
 */
class EnsureStaffRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->isStaff() || ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => 'This account has no access to the back office.']);
        }

        $allowed = $roles === [] ? UserRole::staff() : array_map(UserRole::from(...), $roles);

        abort_unless(in_array($user->role, $allowed, true), 403, 'Your role cannot access this section.');

        return $next($request);
    }
}
