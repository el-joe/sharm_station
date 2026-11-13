<?php

namespace App\DTOs\Partner;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PartnerApplicationData
{
    public function __construct(
        public string $company,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?string $phone = null,
        public ?string $country = null,
        public ?string $website = null,
        public ?string $taxNumber = null,
        public ?string $message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            company: $data['company'],
            name: $data['name'] ?? null,
            email: isset($data['email']) ? strtolower($data['email']) : null,
            password: $data['password'] ?? null,
            phone: $data['phone'] ?? null,
            country: isset($data['country']) ? strtoupper($data['country']) : null,
            website: $data['website'] ?? null,
            taxNumber: $data['tax_number'] ?? null,
            message: $data['message'] ?? null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
