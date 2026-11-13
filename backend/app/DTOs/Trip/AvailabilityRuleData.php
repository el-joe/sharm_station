<?php

namespace App\DTOs\Trip;

use App\Enums\BookingType;

/**
 * Availability settings for one booking type (private: lead time + blackout dates; group: weekdays/dates too).
 */
final readonly class AvailabilityRuleData
{
    /**
     * @param  list<int>  $weekdays  ISO weekdays 1 (Mon) .. 7 (Sun)
     * @param  list<string>  $dates  Y-m-d
     * @param  list<string>  $blackoutDates  Y-m-d
     */
    public function __construct(
        public BookingType $bookingType,
        public int $leadTimeHours = 24,
        public array $weekdays = [],
        public array $dates = [],
        public array $blackoutDates = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(BookingType $bookingType, array $data): self
    {
        return new self(
            bookingType: $bookingType,
            leadTimeHours: (int) ($data['lead_time_hours'] ?? 24),
            weekdays: $bookingType === BookingType::Group ? array_values(array_map('intval', $data['weekdays'] ?? [])) : [],
            dates: $bookingType === BookingType::Group ? self::dateList($data['dates'] ?? null) : [],
            blackoutDates: self::dateList($data['blackout_dates'] ?? null),
        );
    }

    /**
     * Accepts a list or the comma separated string produced by the multi-date picker.
     *
     * @param  list<string>|string|null  $value
     * @return list<string>
     */
    public static function dateList(array|string|null $value): array
    {
        $dates = is_array($value) ? $value : explode(',', (string) $value);
        $dates = array_values(array_unique(array_filter(array_map('trim', $dates))));
        sort($dates);

        return $dates;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'booking_type' => $this->bookingType,
            'lead_time_hours' => $this->leadTimeHours,
            'weekdays' => $this->weekdays,
            'dates' => $this->dates,
            'blackout_dates' => $this->blackoutDates,
        ];
    }
}
