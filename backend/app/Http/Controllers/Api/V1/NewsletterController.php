<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Newsletter\SubscribeData;
use App\Http\Requests\Api\NewsletterRequest;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;

class NewsletterController extends ApiController
{
    /**
     * 201 for a new address, 200 when it was already subscribed (same body).
     */
    public function __invoke(NewsletterRequest $request, NewsletterService $newsletter): JsonResponse
    {
        ['subscriber' => $subscriber, 'created' => $created] = $newsletter->subscribe(SubscribeData::fromRequest($request));

        return response()->json(['data' => [
            'email' => $subscriber->email,
            'locale' => $subscriber->locale,
            'subscribed' => true,
            'already_subscribed' => ! $created,
        ]], $created ? 201 : 200);
    }
}
