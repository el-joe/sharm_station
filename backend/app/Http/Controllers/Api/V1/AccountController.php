<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Account\ProfileData;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\V1\BookingResource;
use App\Http\Resources\V1\UserResource;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Customer account (Bearer token): own bookings, profile and password.
 */
class AccountController extends ApiController
{
    public function __construct(private readonly AccountService $account) {}

    public function bookings(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        return BookingResource::collection($this->account->bookings($request->user(), $perPage));
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        return new UserResource($this->account->updateProfile($request->user(), ProfileData::fromRequest($request))->load('partner'));
    }

    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $this->account->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return response()->noContent();
    }
}
