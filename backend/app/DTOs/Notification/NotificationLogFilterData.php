<?php

namespace App\DTOs\Notification;

use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sending log filters (admin Notifications → Log).
 */
final readonly class NotificationLogFilterData
{
    public function __construct(
        public ?NotificationEvent $event = null,
        public ?NotificationStatus $status = null,
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
            event: NotificationEvent::tryFrom((string) ($data['event'] ?? '')),
            status: NotificationStatus::tryFrom((string) ($data['status'] ?? '')),
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
