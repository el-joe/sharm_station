<?php

namespace App\Services;

use App\Enums\BookingType;
use App\Models\Trip;
use App\Models\TripAvailabilityRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Day rules for trips (SRS 3.4): private = any open day after the lead time and outside blackout dates;
 * group = only the listed weekdays / specific dates. Trips are not seat-inventory scheduled.
 */
class AvailabilityService
{
    public const DEFAULT_LEAD_TIME_HOURS = 24;

    /**
     * Returns a customer-facing reason when the date cannot be booked, or null when it is bookable.
     */
    public function check(Trip $trip, BookingType $bookingType, CarbonInterface $date, ?CarbonInterface $now = null): ?string
    {
        $now = CarbonImmutable::parse($now ?? now());
        $day = CarbonImmutable::parse($date->toDateString());

        if (! $trip->supportsBookingType($bookingType)) {
            return "This trip cannot be booked as {$bookingType->label()}.";
        }

        if ($day->lt($now->startOfDay())) {
            return 'The travel date is in the past.';
        }

        $rule = $this->ruleFor($trip, $bookingType);
        $leadTimeHours = $rule?->lead_time_hours ?? self::DEFAULT_LEAD_TIME_HOURS;
        $earliest = $now->addHours($leadTimeHours)->startOfDay();

        if ($day->lt($earliest)) {
            return "Bookings need at least {$leadTimeHours} hours' notice. The first available date is {$earliest->format('j M Y')}.";
        }

        if (in_array($day->toDateString(), $rule?->blackout_dates ?? [], true)) {
            return "{$day->format('j M Y')} is not available for {$bookingType->label()} bookings.";
        }

        if ($bookingType === BookingType::Private) {
            return null;
        }

        if ($rule === null || ($rule->weekdays === [] || $rule->weekdays === null) && ($rule->dates === [] || $rule->dates === null)) {
            return 'No group departures are scheduled for this trip.';
        }

        if ($this->isGroupDeparture($rule, $day)) {
            return null;
        }

        $weekdays = $this->weekdayNames($rule->weekdays ?? []);

        return $weekdays !== ''
            ? "Group departures run on {$weekdays}. Choose another date."
            : 'There is no group departure on this date. Choose another date.';
    }

    public function isAvailable(Trip $trip, BookingType $bookingType, CarbonInterface $date, ?CarbonInterface $now = null): bool
    {
        return $this->check($trip, $bookingType, $date, $now) === null;
    }

    /**
     * Bookable dates in the next $days days (for calendars).
     *
     * @return list<string>
     */
    public function availableDates(Trip $trip, BookingType $bookingType, ?CarbonInterface $from = null, int $days = 60, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::parse($now ?? now());
        $day = CarbonImmutable::parse(($from ?? $now)->toDateString());
        $dates = [];

        for ($i = 0; $i < $days; $i++, $day = $day->addDay()) {
            if ($this->isAvailable($trip, $bookingType, $day, $now)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    public function ruleFor(Trip $trip, BookingType $bookingType): ?TripAvailabilityRule
    {
        return $trip->availabilityRules->first(fn (TripAvailabilityRule $rule): bool => $rule->booking_type === $bookingType);
    }

    private function isGroupDeparture(TripAvailabilityRule $rule, CarbonImmutable $day): bool
    {
        return in_array($day->toDateString(), $rule->dates ?? [], true)
            || in_array($day->isoWeekday(), array_map('intval', $rule->weekdays ?? []), true);
    }

    /**
     * @param  list<int|string>  $weekdays
     */
    private function weekdayNames(array $weekdays): string
    {
        $names = [1 => 'Mondays', 2 => 'Tuesdays', 3 => 'Wednesdays', 4 => 'Thursdays', 5 => 'Fridays', 6 => 'Saturdays', 7 => 'Sundays'];
        $labels = array_values(array_map(fn ($day): string => $names[(int) $day] ?? '', array_filter($weekdays, fn ($day): bool => isset($names[(int) $day]))));

        if (count($labels) <= 1) {
            return $labels[0] ?? '';
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.$last;
    }
}
