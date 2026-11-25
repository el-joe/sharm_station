<?php

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Enums\DiscountType;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\RoomPricingMode;
use App\Enums\RoomType;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Services\Pricing\RoomPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->engine = app(RoomPricingEngine::class);
    $this->building = Building::factory()->create();
    $this->double = Room::factory()->for($this->building)->create(['name' => ['en' => 'Double'], 'room_type' => RoomType::Double, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 2, 'base_price' => 80]);
    $this->triple = Room::factory()->for($this->building)->create(['name' => ['en' => 'Triple'], 'room_type' => RoomType::Triple, 'max_adults' => 3, 'max_children' => 2, 'max_total' => 3, 'base_price' => 100]);
    $this->secondDouble = Room::factory()->for($this->building)->create(['name' => ['en' => 'Double 2'], 'max_adults' => 2, 'max_children' => 1, 'max_total' => 2, 'base_price' => 80]);
});

/**
 * Two nights (1–3 Dec 2026) for the given rooms; "party" adds the top-level party (adults + child_ages).
 *
 * @param  list<array<string, mixed>>  $rooms
 * @param  array<string, mixed>  $party
 */
function multiRoomQuote(array $rooms, array $party = []): QuoteResultData
{
    $request = RoomQuoteRequestData::fromArray(['check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'rooms' => $rooms, ...$party]);

    return app(RoomPricingEngine::class)->quoteRooms(Room::query()->whereKey($request->roomIds())->get(), $request);
}

test('five guests fit a double and a triple (SRS 7.2 example) and each room is priced per night', function () {
    $quote = multiRoomQuote([
        ['room_id' => $this->double->id, 'adults' => 2],
        ['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [5, 9]],
    ], ['adults' => 3, 'child_ages' => [9, 5]]);

    expect($quote->isValid())->toBeTrue()
        ->and($quote->total)->toBe(360.0)
        ->and($quote->context['rooms'])->toHaveCount(2)
        ->and($quote->context['rooms'][0]['total'])->toBe(160.0)
        ->and($quote->context['rooms'][1]['total'])->toBe(200.0)
        ->and($quote->lines[0]->label)->toStartWith('Room 1 · Double · ')
        ->and($quote->lines[0]->meta['room_id'])->toBe($this->double->id);
});

test('double + double cannot fit five guests: add a room', function () {
    $quote = multiRoomQuote([
        ['room_id' => $this->double->id, 'adults' => 2],
        ['room_id' => $this->secondDouble->id, 'adults' => 1, 'children_ages' => [5]],
    ], ['adults' => 3, 'child_ages' => [5, 9]]);

    expect($quote->errors['rooms'])->toContain('These rooms cannot fit 5 guests, add a room.');
});

test('every guest of the party must be in a room', function () {
    $quote = multiRoomQuote([
        ['room_id' => $this->triple->id, 'adults' => 2],
        ['room_id' => $this->double->id, 'adults' => 1],
    ], ['adults' => 3, 'child_ages' => [7]]);

    expect($quote->errors['rooms'])->toContain('Every guest must be in a room.');
});

test('a room exceeding its capacity, a children-only room and an empty room are rejected', function (array $room, string $key, string $message) {
    $quote = multiRoomQuote([['room_id' => $this->triple->id, 'adults' => 1], ['room_id' => $this->double->id, ...$room]]);

    expect($quote->errors[$key])->toContain($message);
})->with([
    'over capacity' => [['adults' => 2, 'children_ages' => [4]], 'rooms.1.adults', 'Room 2 exceeds its capacity of 2.'],
    'too many adults' => [['adults' => 3], 'rooms.1.adults', 'Room 2 sleeps at most 2 adults.'],
    'children alone' => [['adults' => 0, 'children_ages' => [8]], 'rooms.1.adults', 'A child cannot stay in a room without an adult.'],
    'empty room' => [['adults' => 0], 'rooms.1.adults', 'Room 2 is empty: every booked room needs at least one guest.'],
]);

test('rooms are exclusive: the same room twice, a taken room and rooms of two buildings are rejected', function () {
    expect(multiRoomQuote([['room_id' => $this->double->id, 'adults' => 1], ['room_id' => $this->double->id, 'adults' => 1]])->errors['rooms.1.room_id'])
        ->toContain('Room 2 is selected twice: rooms cannot be shared.');

    RoomAvailability::factory()->create(['room_id' => $this->triple->id, 'date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Booked]);
    expect(multiRoomQuote([['room_id' => $this->double->id, 'adults' => 1], ['room_id' => $this->triple->id, 'adults' => 1]])->errors['check_in'])
        ->toContain('Room 2 is not available on 2 Dec 2026.');

    $elsewhere = Room::factory()->create();
    expect(multiRoomQuote([['room_id' => $this->double->id, 'adults' => 1], ['room_id' => $elsewhere->id, 'adults' => 1]])->errors['rooms'])
        ->toContain('All rooms of a booking must be in the same building.');
});

test('the children policy of the room applies: children refused or below the minimum age', function () {
    $this->triple->childPolicy()->create(['children_allowed' => true, 'min_child_age' => 6, 'adult_from_age' => 12]);
    $this->double->childPolicy()->create(['children_allowed' => false, 'adult_from_age' => 12]);

    expect(multiRoomQuote([['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [3]]])->errors['rooms.0.children_ages'])
        ->toContain('Room 1 accepts children from 6 years.')
        ->and(multiRoomQuote([['room_id' => $this->double->id, 'adults' => 1, 'children_ages' => [8]]])->errors['rooms.0.children_ages'])
        ->toContain('Room 1 does not accept children.');
});

test('per-person rooms price every guest with the age bands and show each guest in the summary (SRS 7.4)', function () {
    $this->triple->update(['pricing_mode' => RoomPricingMode::PerPerson, 'base_price' => 150]);
    $policy = $this->triple->childPolicy()->create(['children_allowed' => true, 'adult_from_age' => 12]);
    $policy->bands()->create(['min_age' => 4, 'max_age' => 11, 'discount_type' => DiscountType::Percent, 'discount_value' => 50]);

    $quote = multiRoomQuote([['room_id' => $this->triple->id, 'adults' => 2, 'children_ages' => [8]]]);
    $room = $quote->context['rooms'][0];

    expect($quote->total)->toBe(750.0)
        ->and($room['guests'])->toHaveCount(3)
        ->and($room['guests'][2])->toMatchArray(['type' => 'child', 'age' => 8, 'total' => 150.0, 'discount' => 150.0]);
});

test('allocation messages follow the request language', function () {
    app()->setLocale('it');

    $quote = multiRoomQuote([['room_id' => $this->double->id, 'adults' => 0, 'children_ages' => [8]]]);

    expect($quote->errors['rooms.0.adults'])->toContain('Un bambino non può stare in una camera senza un adulto.');
});

test('extra beds and cots are priced per night, limited per room, and an extra bed adds a guest place', function () {
    $this->double->update(['max_extra_beds' => 1, 'extra_bed_price' => 25, 'max_cots' => 1, 'cot_price' => 10]);

    $quote = multiRoomQuote([['room_id' => $this->double->id, 'adults' => 2, 'children_ages' => [10], 'extra_beds' => 1, 'cots' => 1]]);
    $extraBed = $quote->linesOfType(QuoteLineData::TYPE_EXTRA_BED)[0];
    $cot = $quote->linesOfType(QuoteLineData::TYPE_COT)[0];

    expect($quote->isValid())->toBeTrue()
        ->and($extraBed->quantity)->toBe(2)
        ->and($extraBed->total)->toBe(50.0)
        ->and($cot->total)->toBe(20.0)
        ->and($quote->total)->toBe(230.0)
        ->and($quote->context['rooms'][0]['extras_total'])->toBe(70.0);

    $tooMany = multiRoomQuote([['room_id' => $this->double->id, 'adults' => 1, 'extra_beds' => 2, 'cots' => 2]]);
    expect($tooMany->errors['rooms.0.extra_beds'])->toContain('Room 1 allows at most 1 extra beds.')
        ->and($tooMany->errors['rooms.0.cots'])->toContain('Room 1 allows at most 1 cots.');

    $withoutBed = multiRoomQuote([['room_id' => $this->double->id, 'adults' => 2, 'children_ages' => [10]]]);
    expect($withoutBed->errors['rooms.0.adults'])->toContain('Room 1 exceeds its capacity of 2.');
});

test('the single-room payload keeps its format and accepts extra beds', function () {
    $this->double->update(['max_extra_beds' => 1, 'extra_bed_price' => 20]);

    $quote = $this->engine->quote($this->double, RoomQuoteRequestData::fromArray(['check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'adults' => 2, 'extra_beds' => 1]));

    expect($quote->isValid())->toBeTrue()
        ->and($quote->lines[0]->label)->toBe('Tue 1 Dec 2026')
        ->and($quote->total)->toBe(200.0)
        ->and($quote->context['room_id'])->toBe($this->double->id);
});

test('quoteRooms reports a room that is not bookable', function () {
    $request = RoomQuoteRequestData::fromArray(['check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'rooms' => [['room_id' => $this->double->id, 'adults' => 1], ['room_id' => 999999, 'adults' => 1]]]);

    $quote = $this->engine->quoteRooms(new Collection([$this->double]), $request);

    expect($quote->errors['rooms.1.room_id'])->toContain('This room is not available.');
});
