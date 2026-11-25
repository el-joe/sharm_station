<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Auth\LoginData;
use App\DTOs\Auth\RegisterData;
use App\DTOs\Auth\ResetPasswordData;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use App\Services\PasswordResetService;
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

    /**
     * Always the same answer, whether or not the e-mail has an account.
     */
    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetService $passwords): JsonResponse
    {
        $passwords->sendResetLink((string) $request->validated('email'));

        return response()->json(['message' => __('account.reset_link_sent')]);
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $passwords): JsonResponse
    {
        $passwords->reset(ResetPasswordData::fromRequest($request));

        return response()->json(['message' => __('account.password_reset')]);
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
