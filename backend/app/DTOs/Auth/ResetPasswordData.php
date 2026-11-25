<?php

namespace App\DTOs\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * New password chosen from the e-mailed link (welcome or "forgot password").
 */
final readonly class ResetPasswordData
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(strtolower(trim((string) $data['email'])), (string) $data['token'], (string) $data['password']);
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Credentials in the shape the password broker expects.
     *
     * @return array{email: string, token: string, password: string}
     */
    public function credentials(): array
    {
        return ['email' => $this->email, 'token' => $this->token, 'password' => $this->password];
    }
}
