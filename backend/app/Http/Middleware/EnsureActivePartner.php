<?php

namespace App\Http\Middleware;

use App\Enums\PartnerStatus;
use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partner panel API: only approved (active) partners; others (pending, suspended, rejected) get 403.
 */
class EnsureActivePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        $partner = $user?->activePartner();

        if (! $partner instanceof Partner) {
            abort(403, $user?->partner?->status === PartnerStatus::Rejected
                ? 'Your partner application was not approved, so the partner panel is not available.'
                : 'Only approved partners can use the partner panel.');
        }

        $request->attributes->set('active_partner', $partner);

        return $next($request);
    }
}
