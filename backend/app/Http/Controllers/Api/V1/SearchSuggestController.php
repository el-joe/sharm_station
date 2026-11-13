<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Search\SuggestQueryData;
use App\Http\Requests\Api\SuggestRequest;
use App\Services\SearchSuggestService;
use Illuminate\Http\JsonResponse;

class SearchSuggestController extends ApiController
{
    public function __construct(private readonly SearchSuggestService $suggestions) {}

    /**
     * GET /search/suggest?scope=stays&q=hurghada+sta → grouped type-ahead suggestions.
     */
    public function __invoke(SuggestRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->suggestions->suggest(SuggestQueryData::fromRequest($request))]);
    }
}
