<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Http\Requests\Api\RoomQuoteRequest;
use App\Http\Resources\V1\QuoteResource;
use App\Services\CurrencyService;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;

class RoomQuoteController extends ApiController
{
    public function __invoke(RoomQuoteRequest $request, RoomService $rooms, RoomPricingEngine $engine, CurrencyService $currencies): JsonResponse
    {
        $room = $rooms->findBookable((int) $request->validated('room_id'));
        abort_if($room === null, 404, 'Room not found.');

        $quote = $engine->quote($room, RoomQuoteRequestData::fromRequest($request, null, $currencies->current(), ...$this->nationality()));
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
