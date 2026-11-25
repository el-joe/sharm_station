<?php

namespace App\Services;

use App\DTOs\Room\IcalFeedData;
use App\Enums\ActivityAction;
use App\Exceptions\DomainValidationException;
use App\Models\Room;
use App\Models\RoomIcalFeed;
use App\Repositories\Contracts\RoomIcalFeedRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * iCal export and import per room (SRS 6.2) to avoid double bookings with other channels.
 *
 * Export: a secret-token URL with one all-day event per run of blocked nights (bookings, holds, manual closures).
 * Nights imported from other calendars are not exported back, so two channels never echo each other's blocks.
 * Import: each external feed's events become closed nights owned by the feed; a sync adds the new nights and
 * frees the ones that disappeared. Nights already taken by a booking, a hold or a manual block are left alone.
 */
class IcalService
{
    public const EXPORT_DAYS_BACK = 30;

    public const HORIZON_DAYS = 730;

    public function __construct(
        private readonly RoomRepositoryInterface $rooms,
        private readonly RoomIcalFeedRepositoryInterface $feeds,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Secret export token of the room, created on first use.
     */
    public function token(Room $room): string
    {
        if (blank($room->ical_token)) {
            $this->rooms->update($room, ['ical_token' => Str::random(40)]);
        }

        return (string) $room->ical_token;
    }

    /**
     * New token: the previous export URL stops working.
     */
    public function regenerateToken(Room $room): string
    {
        $this->rooms->update($room, ['ical_token' => Str::random(40)]);
        $this->activity->log(ActivityAction::Updated, $room, 'Room “'.$room->name.'”: iCal export link regenerated');

        return (string) $room->ical_token;
    }

    public function exportUrl(Room $room): string
    {
        return route('ical.room', ['room' => $room->id, 'token' => $this->token($room)]);
    }

    public function tokenMatches(Room $room, string $token): bool
    {
        return filled($room->ical_token) && hash_equals((string) $room->ical_token, $token);
    }

    /**
     * VCALENDAR text of the room's blocked nights, one event per consecutive run with the same status/booking.
     */
    public function export(Room $room): string
    {
        $today = CarbonImmutable::today();
        $nights = $this->feeds->exportableNights($room, $today->subDays(self::EXPORT_DAYS_BACK), $today->addDays(self::HORIZON_DAYS));
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'sharmstation';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sharm Station Viaggi//Rooms//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($room->building?->name.' · '.$room->name),
        ];

        foreach ($this->runs($nights) as $run) {
            $summary = $run['reference'] !== null ? 'Booked ('.$run['reference'].')' : ($run['status'] === 'closed' ? 'Closed' : 'Not available');
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:room-'.$room->id.'-'.$run['start'].'@'.$host,
                'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                'DTSTART;VALUE=DATE:'.str_replace('-', '', $run['start']),
                'DTEND;VALUE=DATE:'.CarbonImmutable::parse($run['end'])->addDay()->format('Ymd'),
                'SUMMARY:'.$this->escape($summary),
                'TRANSP:OPAQUE',
                'END:VEVENT',
            );
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line): string => $this->fold($line), $lines))."\r\n";
    }

    /**
     * @throws DomainValidationException when the URL is not http(s)
     */
    public function addFeed(Room $room, IcalFeedData $data): RoomIcalFeed
    {
        /** @var RoomIcalFeed $feed */
        $feed = $this->feeds->create(['room_id' => $room->id, 'name' => $data->name, 'url' => $data->url, 'is_active' => true]);
        $this->activity->log(ActivityAction::Created, $room, 'Room “'.$room->name.'”: external calendar “'.$data->name.'” added');

        return $feed;
    }

    /**
     * Removing a feed frees every night it closed (cascade).
     */
    public function removeFeed(RoomIcalFeed $feed): void
    {
        $room = $feed->room;
        $this->feeds->delete($feed);
        $this->activity->log(ActivityAction::Deleted, $room, 'Room “'.$room?->name.'”: external calendar “'.$feed->name.'” removed');
    }

    /**
     * Downloads and applies one feed; the outcome (or the error) is stored on the feed.
     *
     * @return array{added: int, removed: int, skipped: int, error: string|null}
     */
    public function sync(RoomIcalFeed $feed): array
    {
        try {
            $response = Http::connectTimeout(5)->timeout(15)->accept('text/calendar')->get($feed->url);

            if (! $response->successful()) {
                return $this->failed($feed, 'The calendar URL answered HTTP '.$response->status().'.');
            }

            $dates = $this->parseNights($response->body());
        } catch (ConnectionException) {
            return $this->failed($feed, 'The calendar URL could not be reached.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed($feed, 'The calendar could not be read.');
        }

        $result = $this->feeds->syncNights($feed, $dates, CarbonImmutable::today());
        $this->feeds->update($feed, ['last_synced_at' => now(), 'last_nights' => count($dates), 'last_error' => null]);

        return [...$result, 'error' => null];
    }

    /**
     * Scheduled sync of every active feed.
     *
     * @return array{feeds: int, failed: int}
     */
    public function syncAll(): array
    {
        $failed = 0;
        $feeds = $this->feeds->active();

        foreach ($feeds as $feed) {
            $failed += $this->sync($feed)['error'] !== null ? 1 : 0;
        }

        return ['feeds' => $feeds->count(), 'failed' => $failed];
    }

    /**
     * Nights (Y-m-d) covered by the calendar's events: an all-day or timed event from DTSTART to DTEND blocks each
     * night in between (DTEND exclusive); an event without DTEND blocks its first night. Cancelled events are ignored.
     *
     * @return list<string>
     */
    public function parseNights(string $ics): array
    {
        $text = preg_replace("/\r?\n[ \t]/", '', str_replace("\r\n", "\n", $ics)) ?? '';
        $horizon = CarbonImmutable::today()->addDays(self::HORIZON_DAYS);
        $nights = [];

        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $text, $events);

        foreach ($events[1] as $event) {
            $start = $this->dateProperty($event, 'DTSTART');

            if ($start === null || preg_match('/^STATUS:CANCELLED\s*$/mi', $event)) {
                continue;
            }

            $end = $this->dateProperty($event, 'DTEND') ?? $start->addDay();
            $end = $end->lte($start) ? $start->addDay() : $end;
            $end = $end->gt($horizon) ? $horizon : $end;

            for ($night = $start; $night->lt($end); $night = $night->addDay()) {
                $nights[$night->toDateString()] = true;
            }
        }

        $dates = array_keys($nights);
        sort($dates);

        return $dates;
    }

    private function dateProperty(string $event, string $name): ?CarbonImmutable
    {
        if (! preg_match('/^'.$name.'(;[^:\n]*)?:(\d{8})(T(\d{4,6})(Z)?)?\s*$/mi', $event, $match)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Ymd', $match[2]);

        return $date === false ? null : $date;
    }

    /**
     * @param  list<array{date: string, status: string, reference: string|null}>  $nights
     * @return list<array{start: string, end: string, status: string, reference: string|null}>
     */
    private function runs(array $nights): array
    {
        $runs = [];

        foreach ($nights as $night) {
            $last = $runs === [] ? null : $runs[array_key_last($runs)];
            $key = $night['status'] === 'closed' ? 'closed' : 'booking:'.$night['reference'];

            if ($last !== null && $last['key'] === $key && CarbonImmutable::parse($last['end'])->addDay()->toDateString() === $night['date']) {
                $runs[array_key_last($runs)]['end'] = $night['date'];

                continue;
            }

            $runs[] = ['start' => $night['date'], 'end' => $night['date'], 'status' => $night['status'], 'reference' => $night['reference'], 'key' => $key];
        }

        return array_map(fn (array $run): array => array_diff_key($run, ['key' => true]), $runs);
    }

    /**
     * @return array{added: int, removed: int, skipped: int, error: string}
     */
    private function failed(RoomIcalFeed $feed, string $error): array
    {
        $this->feeds->update($feed, ['last_synced_at' => now(), 'last_error' => $error]);

        return ['added' => 0, 'removed' => 0, 'skipped' => 0, 'error' => $error];
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }

    /**
     * RFC 5545 line folding at 75 octets.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = mb_str_split($line, 60);

        return implode("\r\n ", $chunks);
    }
}
