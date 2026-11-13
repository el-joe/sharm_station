<?php

namespace App\DTOs\Auth;

use Illuminate\Foundation\Http\FormRequest;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'web',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(strtolower($data['email']), $data['password'], $data['device_name'] ?? 'web');
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
