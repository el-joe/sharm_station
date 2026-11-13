<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Contact\ContactMessageData;
use App\Http\Requests\Api\ContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends ApiController
{
    public function __invoke(ContactRequest $request, ContactService $contact): JsonResponse
    {
        $message = $contact->submit(ContactMessageData::fromRequest($request));

        return response()->json(['data' => [
            'id' => $message->id,
            'subject' => $message->subject->value,
            'received' => true,
        ]], 201);
    }
}
