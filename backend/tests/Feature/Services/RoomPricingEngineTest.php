<?php

use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Enums\CommissionCategory;
use App\Enums\DiscountType;
use App\Enums\PriceRule;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\RoomPricingMode;
use App\Models\Building;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\SeasonPrice;
use App\Services\Pricing\RoomPricingEngine;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->engine = app(RoomPricingEngine::class);
    $this->room = Room::factory()->create(['base_price' => 80, 'min_nights' => 2, 'max_nights' => 10]);
});

function stay(array $overrides = [], ?Partner $partner = null): RoomQuoteRequestData
{
    return RoomQuoteRequestData::fromArray(['check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'adults' => 2, ...$overrides], $partner);
}

test('each night is priced by its own season or the base price', function () {
    SeasonPrice::factory()->between('2026-11-01', '2026-12-19', 'High season')->create([
        'priceable_type' => 'room',
        'priceable_id' => $this->room->id,
        'adult_price' => null,
        'price_per_night' => 100,
    ]);

    $quote = $this->engine->quote($this->room, stay(['check_in' => '2026-12-18', 'check_out' => '2026-12-21']));

    expect($quote->isValid())->toBeTrue()
        ->and(collect($quote->lines)->pluck('total')->all())->toBe([100.0, 100.0, 80.0])
        ->and($quote->retailTotal)->toBe(280.0)
        ->and($quote->seasonName)->toBe('High season')
        ->and($quote->context['nights'])->toBe(3);
});

test('a date price override wins over the season', function () {
    RoomAvailability::factory()->for($this->room)->create(['date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Open, 'price_override' => 120]);

    $quote = $this->engine->quote($this->room, stay());

    expect($quote->retailTotal)->toBe(280.0);
});

test('minimum and maximum nights are enforced', function () {
    expect($this->engine->quote($this->room, stay(['check_out' => '2026-12-02']))->errors['check_out'])
        ->toContain('This room requires a minimum stay of 2 nights.')
        ->and($this->engine->quote($this->room, stay(['check_out' => '2026-12-15']))->errors['check_out'])
        ->toContain('This room allows a maximum stay of 10 nights.');
});

test('room capacity is enforced', function () {
    $quote = $this->engine->quote($this->room, stay(['adults' => 3, 'children' => 1, 'child_ages' => [5]]));

    expect($quote->errors['adults'])->toContain('This room sleeps at most 2 adults.')
        ->toContain('Room exceeds its capacity of 3.');
});

test('booked or closed nights make the room unavailable', function () {
    RoomAvailability::factory()->for($this->room)->create(['date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Booked]);

    expect($this->engine->quote($this->room, stay())->errors['check_in'])->toContain('This room is not available on 2 Dec 2026.');
});

test('the tax included flag is passed through and no tax line is added', function () {
    $quote = $this->engine->quote($this->room, stay());

    expect($quote->context['tax_included'])->toBeTrue()
        ->and($quote->linesOfType('tax'))->toBe([])
        ->and($quote->retailTotal)->toBe(240.0);
});

test('a building without taxes included adds the tax per night as a separate line', function () {
    $room = Room::factory()->for(Building::factory()->taxesExcluded(6.5))->create(['base_price' => 80]);

    $quote = $this->engine->quote($room, stay());

    expect($quote->context['tax_included'])->toBeFalse()
        ->and($quote->linesOfType('tax')[0]->total)->toBe(19.5)
        ->and($quote->retailTotal)->toBe(259.5);
});

test('per person rooms charge every guest with child band discounts', function () {
    $room = Room::factory()->create(['pricing_mode' => RoomPricingMode::PerPerson, 'base_price' => 50, 'min_nights' => 1]);
    $policy = $room->childPolicy()->create(['adult_from_age' => 12]);
    $policy->bands()->create(['min_age' => 4, 'max_age' => 11, 'discount_type' => DiscountType::Percent, 'discount_value' => 50]);

    $quote = $this->engine->quote($room->fresh(), stay(['check_out' => '2026-12-02', 'children' => 1, 'child_ages' => [8]]));

    expect($quote->retailTotal)->toBe(125.0);
});

test('partners pay the customer price for accommodation (no commission)', function () {
    $partner = Partner::factory()->create();
    partnerOverride($partner, CommissionCategory::SingleDayTrips, 30);

    $quote = $this->engine->quote($this->room, stay(partner: $partner));

    expect($quote->partnerTotal)->toBe($quote->retailTotal)
        ->and($quote->commissionAmount)->toBe(0.0)
        ->and($quote->priceRule)->toBe(PriceRule::None)
        ->and($quote->depositAmount)->toBe(round($quote->retailTotal * 0.3, 2));
});
