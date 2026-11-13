<?php

namespace App\DTOs\Destination;

use Illuminate\Foundation\Http\FormRequest;

final readonly class DestinationData
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     */
    public function __construct(
        public array $name,
        public string $slug,
        public array $description = [],
        public ?string $image = null,
        public int $sort = 0,
        public bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: array_filter((array) $data['name'], fn ($value): bool => filled($value)),
            slug: (string) $data['slug'],
            description: array_filter((array) ($data['description'] ?? []), fn ($value): bool => filled($value)),
            image: filled($data['image'] ?? null) ? $data['image'] : null,
            sort: (int) ($data['sort'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? false),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description === [] ? null : $this->description,
            'image' => $this->image,
            'sort' => $this->sort,
            'is_active' => $this->isActive,
        ];
    }
}
