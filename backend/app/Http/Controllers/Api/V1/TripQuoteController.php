<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pricing\QuoteRequestData;
use App\Http\Requests\Api\TripQuoteRequest;
use App\Http\Resources\V1\QuoteResource;
use App\Services\CurrencyService;
use App\Services\Pricing\TripPricingEngine;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;

class TripQuoteController extends ApiController
{
    public function __invoke(TripQuoteRequest $request, string $slug, TripService $trips, TripPricingEngine $engine, CurrencyService $currencies): JsonResponse
    {
        $trip = $trips->findPublishedBySlug($slug);
        abort_if($trip === null, 404, 'Trip not found.');

        $quote = $engine->quote($trip, QuoteRequestData::fromRequest($request, null, $currencies->current(), ...$this->nationality()));
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
