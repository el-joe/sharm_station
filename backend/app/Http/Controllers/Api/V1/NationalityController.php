<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\NationalityGroup;
use App\Services\NationalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Countries for the nationality gate (CR-10), with their price group and the country detected from the connection.
 */
class NationalityController extends ApiController
{
    public function __invoke(Request $request, NationalityService $nationalities): JsonResponse
    {
        $detected = $nationalities->detectedCountry($request);

        return response()->json([
            'data' => $nationalities->countryList(),
            'detected' => $detected,
            'detected_group' => $detected !== null ? $nationalities->groupFor($detected)->summary() : null,
            'default_group' => $nationalities->defaultGroup()->summary(),
            'groups' => $nationalities->groups()->where('is_active', true)->map(fn (NationalityGroup $group): array => $group->summary())->values(),
        ]);
    }
}
