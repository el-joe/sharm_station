<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partner panel API: only approved (active) partners; others get 403.
 */
class EnsureActivePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->user('sanctum')?->activePartner();
        abort_unless($partner instanceof Partner, 403, 'Only approved partners can use the partner panel.');

        $request->attributes->set('active_partner', $partner);

        return $next($request);
    }
}
