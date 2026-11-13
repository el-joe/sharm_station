<?php

namespace App\DTOs\Auth;

use Illuminate\Foundation\Http\FormRequest;

final readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone = null,
        public string $locale = 'en',
        public string $currency = 'EUR',
        public string $deviceName = 'web',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: strtolower($data['email']),
            password: $data['password'],
            phone: $data['phone'] ?? null,
            locale: $data['locale'] ?? 'en',
            currency: $data['currency'] ?? 'EUR',
            deviceName: $data['device_name'] ?? 'web',
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
