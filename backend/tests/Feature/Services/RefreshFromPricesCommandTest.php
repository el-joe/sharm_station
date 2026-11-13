<?php

use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;

test('the daily command drops expired seasons from the from price', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13'));
    $trip = Trip::factory()->create(['base_price' => 40, 'from_price' => 40, 'private_enabled' => false, 'group_enabled' => true]);
    SeasonPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $trip->id, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-20', 'adult_price' => 25]);
    $room = Room::factory()->create(['base_price' => 80, 'from_price' => 999]);

    $this->artisan('prices:refresh-from')->assertSuccessful();
    expect((float) $trip->fresh()->from_price)->toBe(25.0)->and((float) $room->fresh()->from_price)->toBe(80.0);

    $this->travelTo(CarbonImmutable::parse('2026-11-25'));
    $this->artisan('prices:refresh-from')->assertSuccessful();
    expect((float) $trip->fresh()->from_price)->toBe(40.0);
});

test('the from price refresh is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command ?? '', 'prices:refresh-from'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('10 0 * * *');
});
