<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MetaService;
use Illuminate\Http\JsonResponse;

class MetaController extends ApiController
{
    public function __invoke(MetaService $meta): JsonResponse
    {
        return response()->json(['data' => $meta->meta()]);
    }
}
