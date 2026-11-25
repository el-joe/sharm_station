<?php

namespace App\DTOs\Room;

use Illuminate\Foundation\Http\FormRequest;

/**
 * External calendar to import into a room.
 */
final readonly class IcalFeedData
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(trim((string) $request->validated('name')), trim((string) $request->validated('url')));
    }
}
