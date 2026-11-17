<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Transfer\TransferQuoteRequestData;
use App\Http\Requests\Api\TransferQuoteRequest;
use App\Http\Resources\V1\TransferHubResource;
use App\Http\Resources\V1\VehicleTypeResource;
use App\Services\CurrencyService;
use App\Services\Pricing\TransferPricingService;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransferController extends ApiController
{
    public function __construct(private readonly TransferService $transfers) {}

    public function hubs(): AnonymousResourceCollection
    {
        return TransferHubResource::collection($this->transfers->enabledHubs());
    }

    public function vehicles(): AnonymousResourceCollection
    {
        return VehicleTypeResource::collection($this->transfers->enabledVehicles());
    }

    public function quote(TransferQuoteRequest $request, TransferPricingService $pricing, CurrencyService $currencies): JsonResponse
    {
        $quote = $pricing->quote(TransferQuoteRequestData::fromRequest($request, null, $currencies->current(), ...$this->nationality()));

        return $this->quoteResponse($request, $quote);
    }
}
