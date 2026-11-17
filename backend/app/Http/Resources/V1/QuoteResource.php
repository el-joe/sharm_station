<?php

namespace App\Http\Resources\V1;

use App\DTOs\Pricing\QuoteResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quote payload; partner lines and totals are only present on partner-panel quotes.
 *
 * @property QuoteResultData $resource
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Only partner-panel quotes carry partner prices; public quotes never do (CR-15).
        return $this->resource->toArray(includePartner: $this->resource->isPartnerQuote());
    }
}
