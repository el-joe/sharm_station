<?php

use App\Enums\RoomAvailabilityStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomIcalFeed;
use App\Services\IcalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->room = Room::factory()->create(['name' => ['en' => 'Sea view double']]);
});

function icsCalendar(string ...$events): string
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".implode('', $events)."END:VCALENDAR\r\n";
}

function icsEvent(string $start, string $end, string $extra = ''): string
{
    return "BEGIN:VEVENT\r\nUID:".md5($start.$end)."\r\nDTSTART;VALUE=DATE:{$start}\r\nDTEND;VALUE=DATE:{$end}\r\n{$extra}SUMMARY:Reserved\r\nEND:VEVENT\r\n";
}

test('the export feed lists booked, held and closed nights as all-day events behind the secret token', function () {
    $booking = Booking::factory()->create(['reference' => 'BK-5001']);
    foreach (['2026-12-01', '2026-12-02'] as $date) {
        RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => $date, 'status' => RoomAvailabilityStatus::Booked, 'booking_id' => $booking->id]);
    }
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-10', 'status' => RoomAvailabilityStatus::Closed]);
    $feed = RoomIcalFeed::factory()->for($this->room)->create();
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-20', 'status' => RoomAvailabilityStatus::Closed, 'room_ical_feed_id' => $feed->id]);

    $url = app(IcalService::class)->exportUrl($this->room);
    $body = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();

    expect($body)->toContain('BEGIN:VCALENDAR')
        ->toContain("DTSTART;VALUE=DATE:20261201\r\nDTEND;VALUE=DATE:20261203")
        ->toContain('SUMMARY:Booked (BK-5001)')
        ->toContain("DTSTART;VALUE=DATE:20261210\r\nDTEND;VALUE=DATE:20261211")
        ->not->toContain('20261220');

    $this->get(route('ical.room', ['room' => $this->room->id, 'token' => str_repeat('x', 40)]))->assertNotFound();
});

test('a feed sync closes the nights of its events, frees the ones that disappear and never touches bookings', function () {
    $feed = RoomIcalFeed::factory()->for($this->room)->create(['url' => 'https://calendar.example.test/a.ics']);
    $booking = Booking::factory()->create();
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-03', 'status' => RoomAvailabilityStatus::Booked, 'booking_id' => $booking->id]);

    Http::fakeSequence('calendar.example.test/*')
        ->push(icsCalendar(icsEvent('20261201', '20261204'), icsEvent('20261215', '20261216', "STATUS:CANCELLED\r\n")))
        ->push(icsCalendar(icsEvent('20261202', '20261203')));

    $first = app(IcalService::class)->sync($feed);

    expect($first)->toMatchArray(['added' => 2, 'removed' => 0, 'skipped' => 1, 'error' => null])
        ->and(RoomAvailability::query()->where('room_ical_feed_id', $feed->id)->orderBy('date')->pluck('date')->map->toDateString()->all())->toBe(['2026-12-01', '2026-12-02'])
        ->and(RoomAvailability::query()->where('date', '2026-12-03')->value('status'))->toBe(RoomAvailabilityStatus::Booked);

    $second = app(IcalService::class)->sync($feed);

    expect($second)->toMatchArray(['added' => 0, 'removed' => 1])
        ->and(RoomAvailability::query()->where('room_ical_feed_id', $feed->id)->pluck('date')->map->toDateString()->all())->toBe(['2026-12-02'])
        ->and($feed->fresh()->last_nights)->toBe(1);
});

test('a failing feed keeps its nights and stores the error; the scheduled command syncs every active feed', function () {
    $feed = RoomIcalFeed::factory()->for($this->room)->create(['url' => 'https://calendar.example.test/broken.ics']);
    RoomIcalFeed::factory()->for($this->room)->create(['url' => 'https://calendar.example.test/off.ics', 'is_active' => false]);
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-01', 'status' => RoomAvailabilityStatus::Closed, 'room_ical_feed_id' => $feed->id]);
    Http::fake(['calendar.example.test/broken.ics' => Http::response('Not found', 404)]);

    $this->artisan('rooms:sync-ical')->expectsOutput('Calendars synced: 1, failed: 1')->assertSuccessful();

    expect($feed->fresh()->last_error)->toBe('The calendar URL answered HTTP 404.')
        ->and(RoomAvailability::query()->where('room_ical_feed_id', $feed->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

test('timed events and events without an end block their nights', function () {
    $nights = app(IcalService::class)->parseNights(icsCalendar(
        "BEGIN:VEVENT\r\nDTSTART:20261205T140000Z\r\nDTEND:20261207T100000Z\r\nEND:VEVENT\r\n",
        "BEGIN:VEVENT\r\nDTSTART;VALUE=DATE:20261224\r\nEND:VEVENT\r\n",
    ));

    expect($nights)->toBe(['2026-12-05', '2026-12-06', '2026-12-24']);
});

test('staff add, sync and remove external calendars and renew the export link from the room page', function () {
    $this->actingAs(staffUser());
    Http::fake(['calendar.example.test/*' => Http::response(icsCalendar(icsEvent('20261201', '20261203')))]);

    $this->get(route('admin.rooms.edit', $this->room))->assertOk()->assertSee('Calendar sync')->assertSee('/ical/rooms/'.$this->room->id.'/', false);

    $this->post(route('admin.rooms.ical.store', $this->room), ['name' => 'Booking.com', 'url' => 'https://calendar.example.test/b.ics'])
        ->assertRedirect()->assertSessionHas('success', 'Calendar “Booking.com” added: 2 nights closed.');
    $feed = RoomIcalFeed::query()->sole();

    $this->post(route('admin.rooms.ical.sync', [$this->room, $feed]))->assertSessionHas('success');
    $this->post(route('admin.rooms.ical.store', $this->room), ['name' => 'Bad', 'url' => 'ftp://example.test/x.ics'])->assertSessionHasErrors('url');

    $token = $this->room->fresh()->ical_token;
    $this->post(route('admin.rooms.ical.regenerate', $this->room))->assertRedirect();
    expect($this->room->fresh()->ical_token)->not->toBe($token);

    $this->delete(route('admin.rooms.ical.destroy', [$this->room, $feed]))->assertRedirect();
    expect(RoomAvailability::query()->where('room_id', $this->room->id)->count())->toBe(0);

    $other = RoomIcalFeed::factory()->create();
    $this->post(route('admin.rooms.ical.sync', [$this->room, $other]))->assertNotFound();
});
