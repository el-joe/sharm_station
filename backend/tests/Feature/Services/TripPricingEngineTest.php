<?php

use App\Enums\AddonPricingBasis;
use App\Enums\CommissionCategory;
use App\Enums\PriceRule;
use App\Models\Addon;
use App\Models\Partner;
use App\Models\PartnerCategoryCommission;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Services\Pricing\TripPricingEngine;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->engine = app(TripPricingEngine::class);
});

function units(Trip $trip, array $quantities): array
{
    return collect($quantities)
        ->map(fn (int $quantity, string $name): array => [
            'unit_option_id' => $trip->unitOptions->first(fn ($option) => $option->name === $name)->id,
            'quantity' => $quantity,
        ])
        ->values()
        ->all();
}

describe('per person', function () {
    test('group price is the adult price times the party size', function () {
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->retailTotal)->toBe(100.0)
            ->and($quote->seasonName)->toBeNull()
            ->and($quote->lines[0]->label)->toBe('Adult')
            ->and($quote->lines[0]->quantity)->toBe(4);
    });

    test('children are discounted by age band and older children pay the adult price', function () {
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 2, 'children' => 3, 'child_ages' => [2, 6, 13]]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->retailTotal)->toBe(87.5)
            ->and(collect($quote->lines)->map(fn ($line) => [$line->label, $line->quantity, $line->unitPrice])->all())->toBe([
                ['Adult', 3, 25.0],
                ['Child 0-3 years (free)', 1, 0.0],
                ['Child 4-11 years (50% off)', 1, 12.5],
            ]);
    });

    test('children use the group child price when the trip has no age bands', function () {
        $trip = snorkellingTrip();
        $trip->childPolicy->delete();

        $quote = $this->engine->quote($trip->fresh(), quoteRequest(['adults' => 1, 'children' => 1, 'child_ages' => [7]]));

        expect($quote->retailTotal)->toBe(40.0);
    });

    test('the party cannot exceed the maximum persons per booking', function () {
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 10, 'children' => 3, 'child_ages' => [5, 6, 7]]));

        expect($quote->isValid())->toBeFalse()
            ->and($quote->errors['adults'])->toContain('This trip allows at most 12 persons per booking.');
    });

    test('the party must reach the minimum persons', function () {
        $quote = $this->engine->quote(snorkellingTrip(['min_persons' => 2]), quoteRequest(['adults' => 1]));

        expect($quote->errors['adults'])->toContain('This trip needs at least 2 persons per booking.');
    });

    test('every child needs an age', function () {
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 1, 'children' => 2, 'child_ages' => [5]]));

        expect($quote->errors)->toHaveKey('child_ages');
    });

    test('private bookings use the party size tier', function () {
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['booking_type' => 'private', 'adults' => 3]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->retailTotal)->toBe(165.0)
            ->and($quote->lines[0]->label)->toBe('Private, party of 3-4 (per person)');
    });

    test('a private party with no matching tier is rejected', function () {
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['booking_type' => 'private', 'adults' => 10]));

        expect($quote->errors['adults'])->toContain('No private price is available for a party of 10.');
    });

    test('the season of the travel date overrides the base prices', function () {
        $trip = snorkellingTrip();
        $tier = $trip->privateTiers->firstWhere('min_people', 3);
        SeasonPrice::factory()->between('2026-11-01', '2026-12-19', 'High season')->create([
            'priceable_id' => $trip->id,
            'adult_price' => 30,
            'child_price' => 18,
            'tier_prices' => [$tier->id => 62],
        ]);

        $group = $this->engine->quote($trip, quoteRequest(['travel_date' => '2026-12-15', 'adults' => 2]));
        $private = $this->engine->quote($trip, quoteRequest(['travel_date' => '2026-12-15', 'booking_type' => 'private', 'adults' => 3]));
        $outside = $this->engine->quote($trip, quoteRequest(['travel_date' => '2026-12-21', 'adults' => 2]));

        expect($group->seasonName)->toBe('High season')
            ->and($group->retailTotal)->toBe(60.0)
            ->and($private->retailTotal)->toBe(186.0)
            ->and($outside->seasonName)->toBeNull()
            ->and($outside->retailTotal)->toBe(50.0);
    });

    test('a group date that is not a departure day is rejected', function () {
        $trip = snorkellingTrip();
        $trip->availabilityRules()->where('booking_type', 'group')->update(['weekdays' => json_encode([1])]);

        $quote = $this->engine->quote($trip->fresh(), quoteRequest(['travel_date' => '2026-11-25']));

        expect($quote->errors['travel_date'])->toContain('Group departures run on Mondays. Choose another date.');
    });
});

describe('per unit', function () {
    test('one single and one double buggy for three riders costs 85 euro', function () {
        $trip = buggyTrip();

        $quote = $this->engine->quote($trip, quoteRequest(['adults' => 3, 'units' => units($trip, ['Single buggy' => 1, 'Double buggy' => 1])]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->retailTotal)->toBe(85.0)
            ->and($quote->context['pricing_mode'])->toBe('per_unit')
            ->and(collect($quote->lines)->map(fn ($line) => [$line->label, $line->quantity, $line->total])->all())->toBe([
                ['Single buggy', 1, 35.0],
                ['Double buggy', 1, 50.0],
            ]);
    });

    test('the selected units must seat every traveller', function () {
        $trip = buggyTrip();

        $quote = $this->engine->quote($trip, quoteRequest(['adults' => 3, 'units' => units($trip, ['Double buggy' => 1])]));

        expect($quote->errors['units'])->toContain('The selected units have 2 seats for 3 travellers. Add another unit.');
    });

    test('every unit needs at least one traveller', function () {
        $trip = buggyTrip();

        $quote = $this->engine->quote($trip, quoteRequest(['adults' => 2, 'units' => units($trip, ['Single buggy' => 2, 'Double buggy' => 1])]));

        expect($quote->errors['units'])->toContain('Every unit needs at least one traveller: 3 units for 2 travellers.');
    });

    test('every unit needs at least one adult', function () {
        $trip = buggyTrip();

        $quote = $this->engine->quote($trip, quoteRequest(['adults' => 1, 'children' => 1, 'child_ages' => [9], 'units' => units($trip, ['Double buggy' => 2])]));

        expect($quote->errors['units'])->toContain('Every unit needs at least one adult: 2 units but only 1 adults.');
    });

    test('the quantity of a unit option cannot exceed its maximum', function () {
        $trip = buggyTrip();

        $quote = $this->engine->quote($trip, quoteRequest(['adults' => 5, 'units' => units($trip, ['Single buggy' => 5])]));

        expect($quote->errors['units'])->toContain('You can book at most 4 × Single buggy.');
    });

    test('at least one unit must be chosen and unknown options are rejected', function () {
        $trip = buggyTrip();

        expect($this->engine->quote($trip, quoteRequest(['adults' => 1]))->errors['units'])->toContain('Choose at least one unit.')
            ->and($this->engine->quote($trip, quoteRequest(['adults' => 1, 'units' => [['unit_option_id' => 999, 'quantity' => 1]]]))->errors['units'])
            ->toContain('Unit option #999 is not available for this trip.');
    });

    test('children cannot ride units that do not allow children', function () {
        $trip = buggyTrip();

        $quote = $this->engine->quote($trip, quoteRequest(['adults' => 1, 'children' => 1, 'child_ages' => [10], 'units' => units($trip, ['Single buggy' => 2])]));

        expect($quote->errors['children'])->toContain('Children are not allowed on the selected units.');
    });

    test('season unit prices replace the option prices', function () {
        $trip = buggyTrip();
        [$single, $double] = [$trip->unitOptions[0], $trip->unitOptions[1]];
        SeasonPrice::factory()->between('2026-12-20', '2027-01-06', 'Christmas & New Year')->create([
            'priceable_id' => $trip->id,
            'adult_price' => null,
            'unit_prices' => [$single->id => 45, $double->id => ['price' => 65, 'partner_price' => 55]],
        ]);

        $quote = $this->engine->quote($trip, quoteRequest(['travel_date' => '2027-01-02', 'adults' => 3, 'units' => units($trip, ['Single buggy' => 1, 'Double buggy' => 1])]));

        expect($quote->seasonName)->toBe('Christmas & New Year')
            ->and($quote->retailTotal)->toBe(110.0);
    });
});

describe('add-ons', function () {
    test('add-ons are priced per person, per booking or per unit and required ones are always added', function () {
        $trip = snorkellingTrip();
        $lunch = Addon::factory()->basis(AddonPricingBasis::PerPerson, 12)->create();
        $guide = Addon::factory()->basis(AddonPricingBasis::PerBooking, 40)->create();
        $gopro = Addon::factory()->basis(AddonPricingBasis::PerUnit, 10)->create(['max_quantity' => 5]);
        $parkFee = Addon::factory()->basis(AddonPricingBasis::PerPerson, 5)->required()->create();
        $trip->addons()->attach([$lunch->id, $guide->id, $gopro->id, $parkFee->id]);

        $quote = $this->engine->quote($trip->fresh(), quoteRequest(['adults' => 3, 'addons' => [
            ['addon_id' => $lunch->id],
            ['addon_id' => $guide->id],
            ['addon_id' => $gopro->id, 'quantity' => 2],
        ]]));

        $addonTotals = collect($quote->linesOfType('addon'))->mapWithKeys(fn ($line) => [$line->meta['addon_id'] => $line->total]);

        expect($quote->isValid())->toBeTrue()
            ->and($addonTotals->all())->toEqual([$lunch->id => 36.0, $guide->id => 40.0, $gopro->id => 20.0, $parkFee->id => 15.0])
            ->and($quote->retailTotal)->toBe(75.0 + 111.0);
    });

    test('add-ons not attached to the trip or above their maximum quantity are rejected', function () {
        $trip = buggyTrip();
        $gopro = Addon::factory()->basis(AddonPricingBasis::PerUnit, 10)->create(['max_quantity' => 2]);
        $trip->addons()->attach($gopro->id);
        $other = Addon::factory()->create();

        $quote = $this->engine->quote($trip->fresh(), quoteRequest(['adults' => 3, 'units' => units($trip, ['Family buggy' => 1]), 'addons' => [
            ['addon_id' => $gopro->id, 'quantity' => 3],
            ['addon_id' => $other->id],
        ]]));

        expect($quote->errors['addons'])->toContain('Choose between 1 and 2 × Lunch upgrade.')
            ->toContain("Add-on #{$other->id} is not available for this trip.");
    });
});

describe('partner pricing', function () {
    test('an explicit item partner price wins over the category commission', function () {
        $trip = buggyTrip();
        $trip->unitOptions[0]->update(['partner_price' => 30]);
        $partner = Partner::factory()->create(['default_commission' => 10]);
        PartnerCategoryCommission::factory()->for($partner)->create(['category' => CommissionCategory::Trips, 'commission_percent' => 15]);

        $quote = $this->engine->quote($trip->fresh(), quoteRequest(['adults' => 3, 'units' => units($trip, ['Single buggy' => 1, 'Double buggy' => 1])], $partner));

        expect($quote->lines[0]->partnerTotal)->toBe(30.0)
            ->and($quote->lines[0]->priceRule)->toBe(PriceRule::PartnerPrice)
            ->and($quote->lines[1]->partnerTotal)->toBe(42.5)
            ->and($quote->lines[1]->priceRule)->toBe(PriceRule::CategoryCommission)
            ->and($quote->retailTotal)->toBe(85.0)
            ->and($quote->partnerTotal)->toBe(72.5)
            ->and($quote->total)->toBe(72.5)
            ->and($quote->commissionAmount)->toBe(12.5)
            ->and($quote->priceRule)->toBe(PriceRule::Mixed);
    });

    test('the category commission wins over the default commission', function () {
        $partner = Partner::factory()->create(['default_commission' => 10]);
        PartnerCategoryCommission::factory()->for($partner)->create(['category' => CommissionCategory::Trips, 'commission_percent' => 15]);

        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4], $partner));

        expect($quote->partnerTotal)->toBe(85.0)
            ->and($quote->priceRule)->toBe(PriceRule::CategoryCommission);
    });

    test('the default commission applies when the partner has no commission for the category', function () {
        $partner = Partner::factory()->create(['default_commission' => 10]);
        PartnerCategoryCommission::factory()->for($partner)->create(['category' => CommissionCategory::NileTrips, 'commission_percent' => 20]);

        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4], $partner));

        expect($quote->partnerTotal)->toBe(90.0)
            ->and($quote->priceRule)->toBe(PriceRule::DefaultCommission);
    });

    test('the retail price applies when the partner has no commission at all', function () {
        $partner = Partner::factory()->withoutDefaultCommission()->create();

        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4], $partner));

        expect($quote->partnerTotal)->toBe(100.0)
            ->and($quote->priceRule)->toBe(PriceRule::Retail);
    });

    test('nile trips use the nile trips commission', function () {
        $partner = Partner::factory()->create(['default_commission' => 10]);
        PartnerCategoryCommission::factory()->for($partner)->create(['category' => CommissionCategory::NileTrips, 'commission_percent' => 20]);

        $quote = $this->engine->quote(snorkellingTrip(['type' => 'nile']), quoteRequest(['adults' => 4], $partner));

        expect($quote->partnerTotal)->toBe(80.0);
    });

    test('a pending partner gets retail prices only', function () {
        $partner = Partner::factory()->pending()->create(['default_commission' => 10]);

        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4], $partner));

        expect($quote->partnerTotal)->toBeNull()
            ->and($quote->total)->toBe(100.0);
    });

    test('child band discounts are applied to the explicit partner price', function () {
        $partner = Partner::factory()->create(['default_commission' => 10]);

        $quote = $this->engine->quote(snorkellingTrip(['partner_price' => 20]), quoteRequest(['adults' => 1, 'children' => 1, 'child_ages' => [5]], $partner));

        expect($quote->partnerTotal)->toBe(30.0)
            ->and($quote->priceRule)->toBe(PriceRule::PartnerPrice);
    });

    test('partner fields are left out of the public array', function () {
        $partner = Partner::factory()->create(['default_commission' => 10]);
        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 2], $partner));

        expect($quote->toArray())->not->toHaveKeys(['partner_total', 'commission_amount', 'price_rule'])
            ->and($quote->toArray()['lines'][0])->not->toHaveKey('partner_total')
            ->and($quote->toArray(includePartner: true))->toHaveKeys(['partner_total', 'commission_amount', 'price_rule']);
    });
});

describe('deposit and currency', function () {
    test('deposit and balance are rounded to two decimals', function () {
        $quote = $this->engine->quote(snorkellingTrip(['base_price' => 23.5, 'deposit_percent' => 33.33]), quoteRequest(['adults' => 2]));

        expect($quote->total)->toBe(47.0)
            ->and($quote->depositAmount)->toBe(15.67)
            ->and($quote->balanceAmount)->toBe(31.33);
    });

    test('the deposit is computed on the partner total for partners', function () {
        $partner = Partner::factory()->create(['default_commission' => 10]);

        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4], $partner));

        expect($quote->depositAmount)->toBe(27.0)
            ->and($quote->balanceAmount)->toBe(63.0);
    });

    test('totals are converted to the requested currency', function () {
        seedCurrencies();

        $quote = $this->engine->quote(snorkellingTrip(), quoteRequest(['adults' => 4], currency: 'USD'));

        expect($quote->currency)->toBe('USD')
            ->and($quote->exchangeRate)->toBe(1.08)
            ->and($quote->converted)->toBe(['total' => 108.0, 'deposit' => 32.4, 'balance' => 75.6]);
    });
});
