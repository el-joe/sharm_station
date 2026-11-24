<?php

namespace App\DTOs\Activity;

use App\Enums\ActivityAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Activity log list filters.
 */
final readonly class ActivityFilterData
{
    public function __construct(
        public ?int $causerId = null,
        public ?ActivityAction $action = null,
        public ?string $subjectType = null,
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
        public ?string $search = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $date = fn (string $key): ?CarbonImmutable => filled($data[$key] ?? null) ? CarbonImmutable::parse($data[$key]) : null;

        return new self(
            causerId: filled($data['causer_id'] ?? null) ? (int) $data['causer_id'] : null,
            action: ActivityAction::tryFrom((string) ($data['action'] ?? '')),
            subjectType: filled($data['subject_type'] ?? null) ? (string) $data['subject_type'] : null,
            dateFrom: $date('date_from'),
            dateTo: $date('date_to'),
            search: filled($data['search'] ?? null) ? trim((string) $data['search']) : null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
