<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Partner\PartnerApplicationData;
use App\Http\Requests\Api\PartnerApplyRequest;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;

class PartnerApplicationController extends ApiController
{
    public function __invoke(PartnerApplyRequest $request, PartnerService $partners): JsonResponse
    {
        $result = $partners->apply(PartnerApplicationData::fromRequest($request), $request->user('sanctum'));

        return response()->json([
            'data' => [
                'id' => $result['partner']->id,
                'company' => $result['partner']->company,
                'status' => $result['partner']->status->value,
                'message' => 'Thank you. Your application is pending approval.',
            ],
            'token' => $result['token'],
        ], 201);
    }
}
