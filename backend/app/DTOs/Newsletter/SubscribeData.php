<?php

namespace App\DTOs\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

final readonly class SubscribeData
{
    public function __construct(
        public string $email,
        public string $locale = 'en',
        public ?string $source = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            email: mb_strtolower(trim((string) $data['email'])),
            locale: $data['locale'] ?? app()->getLocale(),
            source: $data['source'] ?? null,
        );
    }
}
