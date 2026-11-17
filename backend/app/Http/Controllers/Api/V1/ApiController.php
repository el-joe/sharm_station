<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pricing\QuoteResultData;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\QuoteResource;
use App\Services\NationalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * Nationality of the request (?nationality= / X-Nationality / profile) and the group it prices with.
     *
     * @return array{0: string|null, 1: int}
     */
    protected function nationality(): array
    {
        $nationalities = app(NationalityService::class);

        return [$nationalities->currentCode(), $nationalities->currentGroup()->id];
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
