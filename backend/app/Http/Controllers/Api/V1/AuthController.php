<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Auth\LoginData;
use App\DTOs\Auth\RegisterData;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register(RegisterData::fromRequest($request));

        return response()->json([
            'data' => (new UserResource($result['user']->load('partner')))->resolve($request),
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(LoginData::fromRequest($request));

        return response()->json([
            'data' => (new UserResource($result['user']->load('partner')))->resolve($request),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->user());

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('partner'));
    }
}
