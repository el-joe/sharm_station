<?php

use App\DTOs\Pricing\SeasonPriceData;
use App\Exceptions\DomainValidationException;
use App\Exceptions\SeasonOverlapException;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Services\SeasonPriceService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->service = app(SeasonPriceService::class);
    $this->trip = Trip::factory()->create(['base_price' => 25]);
});

function seasonFor(Trip $trip, string $name, string $startsOn, string $endsOn, array $attributes = []): SeasonPrice
{
    return SeasonPrice::factory()->between($startsOn, $endsOn, $name)->create([
        'priceable_type' => 'trip',
        'priceable_id' => $trip->id,
        ...$attributes,
    ]);
}

test('it selects the season containing the date and falls back to the base price outside every season', function () {
    $high = seasonFor($this->trip, 'High season', '2026-11-01', '2026-12-19');

    expect($this->service->forDate($this->trip, CarbonImmutable::parse('2026-11-01'))?->is($high))->toBeTrue()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2026-12-19'))?->is($high))->toBeTrue()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2026-12-20')))->toBeNull()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2026-10-31')))->toBeNull();
});

test('a dated season crossing the year end covers early January of the next year', function () {
    $christmas = seasonFor($this->trip, 'Christmas & New Year', '2026-12-20', '2027-01-06');

    expect($this->service->forDate($this->trip, CarbonImmutable::parse('2027-01-03'))?->is($christmas))->toBeTrue()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2026-12-31'))?->is($christmas))->toBeTrue()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2027-01-07')))->toBeNull()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2027-12-25')))->toBeNull();
});

test('a recurring yearly season crossing the year end applies every year', function () {
    $christmas = seasonFor($this->trip, 'Christmas & New Year', '2026-12-20', '2027-01-06', ['recurring_yearly' => true]);

    expect($this->service->forDate($this->trip, CarbonImmutable::parse('2029-12-25'))?->is($christmas))->toBeTrue()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2028-01-02'))?->is($christmas))->toBeTrue()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2028-01-07')))->toBeNull()
        ->and($this->service->forDate($this->trip, CarbonImmutable::parse('2028-06-01')))->toBeNull();
});

test('inactive seasons are ignored', function () {
    seasonFor($this->trip, 'High season', '2026-11-01', '2026-12-19', ['is_active' => false]);

    expect($this->service->forDate($this->trip, CarbonImmutable::parse('2026-11-20')))->toBeNull();
});

test('a dated season wins over a recurring one on the same day', function () {
    seasonFor($this->trip, 'Recurring high', '2025-11-01', '2025-12-19', ['recurring_yearly' => true]);
    $special = seasonFor($this->trip, 'Special', '2026-11-20', '2026-11-22');

    expect($this->service->forDate($this->trip, CarbonImmutable::parse('2026-11-21'))?->is($special))->toBeTrue();
});

test('an overlapping season is rejected with the overlapping dates', function () {
    seasonFor($this->trip, 'High season', '2026-11-01', '2026-12-19');

    $data = SeasonPriceData::fromArray(['name' => 'Christmas & New Year', 'starts_on' => '2026-12-15', 'ends_on' => '2027-01-06', 'adult_price' => 35]);

    expect(fn () => $this->service->save($this->trip, $data))
        ->toThrow(SeasonOverlapException::class, '“Christmas & New Year” overlaps “High season” on 15–19 Dec 2026.');

    expect(SeasonPrice::query()->count())->toBe(1);
});

test('a dated season overlapping a recurring season in a later year is rejected', function () {
    seasonFor($this->trip, 'Christmas & New Year', '2026-12-20', '2027-01-06', ['recurring_yearly' => true]);

    $data = SeasonPriceData::fromArray(['name' => 'New Year special', 'starts_on' => '2028-12-30', 'ends_on' => '2029-01-02', 'adult_price' => 40]);

    expect(fn () => $this->service->validateNoOverlap($this->trip, $data))
        ->toThrow(SeasonOverlapException::class, '“New Year special” overlaps “Christmas & New Year” on 30 Dec 2028 – 2 Jan 2029.');
});

test('adjacent seasons, inactive seasons and the season being edited do not count as overlaps', function () {
    $high = seasonFor($this->trip, 'High season', '2026-11-01', '2026-12-19');

    $this->service->save($this->trip, SeasonPriceData::fromArray(['name' => 'Christmas', 'starts_on' => '2026-12-20', 'ends_on' => '2027-01-06', 'adult_price' => 35]));
    $this->service->save($this->trip, SeasonPriceData::fromArray(['name' => 'Draft', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-30', 'is_active' => false]));
    $this->service->save($this->trip, SeasonPriceData::fromArray(['name' => 'High season', 'starts_on' => '2026-10-25', 'ends_on' => '2026-12-19', 'adult_price' => 31]), $high);

    expect(SeasonPrice::query()->count())->toBe(3)
        ->and((float) $high->fresh()->adult_price)->toBe(31.0);
});

test('a season ending before it starts is rejected', function () {
    $data = SeasonPriceData::fromArray(['name' => 'Broken', 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-01']);

    expect(fn () => $this->service->save($this->trip, $data))->toThrow(DomainValidationException::class);
});

test('saving a season refreshes the trip from price with the lowest current or future price', function () {
    $this->service->save($this->trip, SeasonPriceData::fromArray(['name' => 'Low', 'starts_on' => '2027-01-07', 'ends_on' => '2027-03-31', 'adult_price' => 19]));
    $this->service->save($this->trip, SeasonPriceData::fromArray(['name' => 'Past summer', 'starts_on' => '2026-06-01', 'ends_on' => '2026-09-30', 'adult_price' => 9]));

    expect((float) $this->trip->fresh()->from_price)->toBe(19.0);
});

test('the price list shows current and upcoming seasons in date order with the current one flagged', function () {
    seasonFor($this->trip, 'Low season', '2027-01-07', '2027-03-31', ['recurring_yearly' => true]);
    seasonFor($this->trip, 'High season', '2026-11-01', '2026-12-19', ['recurring_yearly' => true]);
    seasonFor($this->trip, 'Expired', '2026-06-01', '2026-06-30');

    $list = $this->service->priceList($this->trip);

    expect($list->map(fn (array $row): string => $row['season']->name)->all())->toBe(['High season', 'Low season'])
        ->and($list[0]['is_current'])->toBeTrue()
        ->and($list[1]['starts_on']->toDateString())->toBe('2027-01-07');
});

test('copy to next year shifts the dates by one year', function () {
    $high = seasonFor($this->trip, 'High season', '2026-11-01', '2026-12-19');

    $copy = $this->service->copyToNextYear($high);

    expect($copy->starts_on->toDateString())->toBe('2027-11-01')
        ->and($copy->ends_on->toDateString())->toBe('2027-12-19');
});
