<?php

namespace App\DTOs\Nationality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final readonly class NationalityGroupData
{
    /**
     * @param  array<string, string>  $name
     * @param  list<string>  $countries  ISO-2 codes assigned to the group
     */
    public function __construct(
        public array $name,
        public int $sort,
        public bool $isDefault,
        public bool $isActive,
        public array $countries,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: array_filter($data['name'] ?? [], fn ($value): bool => filled($value)),
            sort: (int) ($data['sort'] ?? 0),
            isDefault: (bool) ($data['is_default'] ?? false),
            isActive: (bool) ($data['is_active'] ?? true),
            countries: array_values(array_unique(array_map(fn ($code): string => strtoupper((string) $code), $data['countries'] ?? []))),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public function code(): string
    {
        return Str::slug($this->name['en'] ?? reset($this->name), '_');
    }
}
