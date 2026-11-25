<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Http\Requests\Api\RoomQuoteRequest;
use App\Http\Resources\V1\QuoteResource;
use App\Services\CurrencyService;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;

/**
 * Stay quote: one room (room_id + adults + child_ages) or several rooms of one building with their guests (rooms[]).
 */
class RoomQuoteController extends ApiController
{
    public function __invoke(RoomQuoteRequest $request, RoomService $rooms, RoomPricingEngine $engine, CurrencyService $currencies): JsonResponse
    {
        $data = RoomQuoteRequestData::fromRequest($request, null, $currencies->current(), ...$this->nationality());
        $found = $rooms->findBookableMany($data->roomIds());
        abort_if($found->isEmpty(), 404, 'Room not found.');

        $quote = $engine->quoteRooms($found, $data);
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
