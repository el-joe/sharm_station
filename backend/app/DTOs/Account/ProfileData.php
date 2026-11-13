<?php

namespace App\DTOs\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Customer profile changes; only the keys sent are updated.
 */
final readonly class ProfileData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        if (array_key_exists('newsletter', $data)) {
            $data['newsletter'] = (bool) $data['newsletter'];
        }

        return new self($data);
    }
}
