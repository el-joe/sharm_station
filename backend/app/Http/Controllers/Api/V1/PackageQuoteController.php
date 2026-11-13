<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Pricing\QuoteRequestData;
use App\Http\Requests\Api\PackageQuoteRequest;
use App\Services\CurrencyService;
use App\Services\PackageService;
use App\Services\Pricing\PackagePricingEngine;
use Illuminate\Http\JsonResponse;

class PackageQuoteController extends ApiController
{
    public function __invoke(PackageQuoteRequest $request, string $slug, PackageService $packages, PackagePricingEngine $engine, CurrencyService $currencies): JsonResponse
    {
        $package = $packages->findPublishedBySlug($slug);
        abort_if($package === null, 404, 'Package not found.');

        $quote = $engine->quote($package, QuoteRequestData::fromRequest($request, $this->partner($request), $currencies->current()));

        return $this->quoteResponse($request, $quote);
    }
}
