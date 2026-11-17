<?php

use App\DTOs\Pricing\RoomQuoteRequestData;
use App\DTOs\Pricing\SeasonPriceData;
use App\Enums\PriceSource;
use App\Exceptions\SeasonOverlapException;
use App\Models\ProductPrice;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Services\NationalityService;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\Pricing\TripPricingEngine;
use App\Services\SeasonPriceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
});

function groupBase(object $product, string $groupCode, array $prices): ProductPrice
{
    return ProductPrice::factory()->create([
        'priceable_type' => $product->getMorphClass(),
        'priceable_id' => $product->id,
        'nationality_group_id' => nationalityGroup($groupCode)->id,
        'adult_price' => null,
        'child_price' => null,
        ...$prices,
    ]);
}

describe('nationality → group', function () {
    test('countries resolve to their group and everything else to the default group', function () {
        $service = app(NationalityService::class);

        expect($service->groupFor('EG')->code)->toBe('egyptian')
            ->and($service->groupFor('sa')->code)->toBe('arab_countries')
            ->and($service->groupFor('GB')->code)->toBe('europe')
            ->and($service->groupFor('CH')->code)->toBe('europe')
            ->and($service->groupFor('US')->code)->toBe('rest_of_world')
            ->and($service->groupFor('XX')->code)->toBe('rest_of_world')
            ->and($service->groupFor(null)->code)->toBe('rest_of_world')
            ->and($service->defaultGroup()->is_default)->toBeTrue();
    });

    test('an inactive group falls back to the default group', function () {
        nationalityGroup('europe')->update(['is_active' => false]);

        expect(app(NationalityService::class)->groupFor('IT')->code)->toBe('rest_of_world');
    });

    test('the detected country comes from the CF-IPCountry or X-Country-Code header', function () {
        $service = app(NationalityService::class);

        expect($service->detectedCountry(Request::create('/', server: ['HTTP_CF_IPCOUNTRY' => 'eg'])))->toBe('EG')
            ->and($service->detectedCountry(Request::create('/', server: ['HTTP_X_COUNTRY_CODE' => 'IT'])))->toBe('IT')
            ->and($service->detectedCountry(Request::create('/', server: ['HTTP_CF_IPCOUNTRY' => 'XX'])))->toBeNull()
            ->and($service->detectedCountry(Request::create('/')))->toBeNull();
    });
});

describe('price resolution order', function () {
    beforeEach(function () {
        $this->trip = snorkellingTrip(['base_price' => 25]);
        groupBase($this->trip, 'europe', ['adult_price' => 30]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->between('2026-12-01', '2026-12-10', 'December')->create(['adult_price' => 35]);
        SeasonPrice::factory()->for($this->trip, 'priceable')->forGroup(nationalityGroup('egyptian'))->between('2026-12-05', '2026-12-08', 'Egyptian December')->create(['adult_price' => 18]);
        $this->resolver = app(PriceResolver::class);
    });

    test('group season → all-groups season → group base → product base', function () {
        $resolve = fn (string $code, string $date) => $this->resolver->resolve($this->trip->fresh(), nationalityGroup($code)->id, CarbonImmutable::parse($date));

        $groupSeason = $resolve('egyptian', '2026-12-06');
        $allGroups = $resolve('europe', '2026-12-06');
        $egyptianOutsideOwnSeason = $resolve('egyptian', '2026-12-02');
        $groupBase = $resolve('europe', '2026-11-20');
        $productBase = $resolve('rest_of_world', '2026-11-20');

        expect([$groupSeason->adult(), $groupSeason->source('adult_price')])->toBe([18.0, PriceSource::GroupSeason])
            ->and($groupSeason->seasonName())->toBe('Egyptian December')
            ->and([$allGroups->adult(), $allGroups->source('adult_price')])->toBe([35.0, PriceSource::AllGroupsSeason])
            ->and([$egyptianOutsideOwnSeason->adult(), $egyptianOutsideOwnSeason->source('adult_price')])->toBe([35.0, PriceSource::AllGroupsSeason])
            ->and([$groupBase->adult(), $groupBase->source('adult_price')])->toBe([30.0, PriceSource::GroupBase])
            ->and([$productBase->adult(), $productBase->source('adult_price')])->toBe([25.0, PriceSource::ProductBase]);
    });

    test('each field falls back on its own (blank season cells use the next layer)', function () {
        SeasonPrice::query()->where('nationality_group_id', nationalityGroup('egyptian')->id)->update(['child_price' => null]);
        groupBase($this->trip, 'egyptian', ['adult_price' => 15, 'child_price' => 9]);

        $prices = $this->resolver->resolve($this->trip->fresh(), nationalityGroup('egyptian')->id, CarbonImmutable::parse('2026-12-06'));

        expect($prices->adult())->toBe(18.0)
            ->and($prices->child())->toBe(9.0)
            ->and($prices->source('child_price'))->toBe(PriceSource::GroupBase);
    });

    test('a recurring group season crossing the year end applies in January', function () {
        SeasonPrice::factory()->for($this->trip, 'priceable')->forGroup(nationalityGroup('europe'))->recurring()
            ->between('2025-12-20', '2026-01-06', 'Christmas')->create(['adult_price' => 50]);

        $prices = $this->resolver->resolve($this->trip->fresh(), nationalityGroup('europe')->id, CarbonImmutable::parse('2027-01-03'));

        expect($prices->adult())->toBe(50.0)->and($prices->seasonName())->toBe('Christmas');
    });

    test('trip quotes are priced for the nationality and report the group used', function () {
        $engine = app(TripPricingEngine::class);

        $egyptian = $engine->quote($this->trip->fresh(), quoteRequest(['adults' => 2, 'travel_date' => '2026-12-06'], nationality: 'EG'));
        $european = $engine->quote($this->trip->fresh(), quoteRequest(['adults' => 2, 'travel_date' => '2026-11-25'], nationality: 'IT'));
        $other = $engine->quote($this->trip->fresh(), quoteRequest(['adults' => 2, 'travel_date' => '2026-11-25']));

        expect($egyptian->retailTotal)->toBe(36.0)
            ->and($egyptian->nationality)->toMatchArray(['code' => 'EG'])
            ->and($egyptian->nationality['group']['code'])->toBe('egyptian')
            ->and($egyptian->lines[0]->meta['price_source'])->toBe('group_season')
            ->and($european->retailTotal)->toBe(60.0)
            ->and($other->retailTotal)->toBe(50.0)
            ->and($other->nationality['group']['code'])->toBe('rest_of_world');
    });

    test('the from price is computed per group', function () {
        $service = app(SeasonPriceService::class);

        expect($service->fromPrice($this->trip->fresh(), null, nationalityGroup('egyptian')->id))->toBe(18.0)
            ->and($service->fromPrice($this->trip->fresh(), null, nationalityGroup('europe')->id))->toBe(30.0)
            ->and($service->fromPrice($this->trip->fresh(), null, nationalityGroup('rest_of_world')->id))->toBe(25.0);
    });
});

test('room nights are priced one by one for each group', function () {
    $room = Room::factory()->create(['base_price' => 100, 'min_nights' => 1]);
    groupBase($room, 'egyptian', ['price_per_night' => 60]);
    SeasonPrice::factory()->between('2026-12-01', '2026-12-02', 'Peak')->create(['priceable_type' => 'room', 'priceable_id' => $room->id, 'adult_price' => null, 'price_per_night' => 150]);
    SeasonPrice::factory()->forGroup(nationalityGroup('egyptian'))->between('2026-12-02', '2026-12-03', 'Local peak')
        ->create(['priceable_type' => 'room', 'priceable_id' => $room->id, 'adult_price' => null, 'price_per_night' => 80]);
    $engine = app(RoomPricingEngine::class);
    $stay = fn (string $code) => RoomQuoteRequestData::fromArray(['check_in' => '2026-11-30', 'check_out' => '2026-12-04', 'adults' => 2], nationalityCode: $code);

    $egyptian = $engine->quote($room->fresh(), $stay('EG'));
    $german = $engine->quote($room->fresh(), $stay('DE'));

    expect(collect($egyptian->lines)->map(fn ($line) => [$line->unitPrice, $line->meta['price_source']])->all())->toBe([
        [60.0, 'group_base'], [150.0, 'all_groups_season'], [80.0, 'group_season'], [80.0, 'group_season'],
    ])
        ->and($egyptian->retailTotal)->toBe(370.0)
        ->and(collect($german->lines)->pluck('unitPrice')->all())->toBe([100.0, 150.0, 150.0, 100.0])
        ->and($german->retailTotal)->toBe(500.0);
});

describe('season overlap per group', function () {
    beforeEach(function () {
        $this->trip = Trip::factory()->create();
        $this->service = app(SeasonPriceService::class);
        $this->row = fn (string $name, string $from, string $to, ?string $group = null): array => ['id' => null, 'data' => SeasonPriceData::fromArray([
            'name' => ['en' => $name], 'starts_on' => $from, 'ends_on' => $to, 'adult_price' => 40,
            'nationality_group_id' => $group !== null ? nationalityGroup($group)->id : null,
        ])];
    });

    test('seasons of different groups, or a group and all groups, may overlap', function () {
        $this->service->sync($this->trip, [
            ($this->row)('All', '2026-12-01', '2026-12-31'),
            ($this->row)('Europe', '2026-12-10', '2026-12-20', 'europe'),
            ($this->row)('Egypt', '2026-12-10', '2026-12-20', 'egyptian'),
        ]);

        expect(SeasonPrice::query()->whereMorphedTo('priceable', $this->trip)->count())->toBe(3);
    });

    test('two seasons of the same group cannot overlap', function () {
        expect(fn () => $this->service->sync($this->trip, [
            ($this->row)('Spring', '2027-04-01', '2027-04-30', 'europe'),
            ($this->row)('Easter', '2027-03-28', '2027-04-12', 'europe'),
        ]))->toThrow(SeasonOverlapException::class, '“Easter” (Europe) overlaps “Spring” on 1–12 Apr 2027.');

        expect(fn () => $this->service->sync($this->trip, [
            ($this->row)('Spring', '2027-04-01', '2027-04-30'),
            ($this->row)('Easter', '2027-03-28', '2027-04-12'),
        ]))->toThrow(SeasonOverlapException::class, '“Easter” overlaps “Spring” on 1–12 Apr 2027.');
    });
});
