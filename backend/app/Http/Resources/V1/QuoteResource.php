<?php

namespace App\Http\Resources\V1;

use App\DTOs\Pricing\QuoteResultData;
use App\Http\Resources\V1\Concerns\PresentsPrices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property QuoteResultData $resource
 */
class QuoteResource extends JsonResource
{
    use PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray(includePartner: $this->partner($request) !== null && $this->resource->isPartnerQuote());
    }
}
