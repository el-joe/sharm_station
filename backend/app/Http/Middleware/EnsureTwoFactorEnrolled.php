<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the "require two-factor for super admins" setting is on, a super admin without 2FA can only open
 * the security page (and sign out) until they enrol.
 */
class EnsureTwoFactorEnrolled
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasTwoFactorEnabled() && ! $request->routeIs('admin.two-factor.*', 'admin.logout') && $this->twoFactor->isRequiredFor($user)) {
            return redirect()->route('admin.two-factor.show')->with('info', 'Set up two-factor authentication to continue.');
        }

        return $next($request);
    }
}
