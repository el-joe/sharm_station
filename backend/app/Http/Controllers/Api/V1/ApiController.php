<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pricing\QuoteResultData;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\QuoteResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * Approved partner resolved by the SetLocaleAndCurrency middleware, if any.
     */
    protected function partner(Request $request): ?Partner
    {
        $partner = $request->attributes->get('partner');

        return $partner instanceof Partner ? $partner : null;
    }

    /**
     * Quote payload: 200 when valid, 422 with the same payload and the errors otherwise.
     */
    protected function quoteResponse(Request $request, QuoteResultData $quote): JsonResponse
    {
        $payload = (new QuoteResource($quote))->resolve($request);

        if (! $quote->isValid()) {
            return response()->json([
                'message' => collect($quote->errors)->flatten()->first(),
                'errors' => $quote->errors,
                'data' => $payload,
            ], 422);
        }

        return response()->json(['data' => $payload]);
    }
}
