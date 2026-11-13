<?php

use App\Enums\PackagePriceMode;
use App\Enums\UserRole;
use App\Models\Destination;
use App\Models\Package;
use App\Models\SeasonPrice;
use App\Models\Trip;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
    $this->cairo = Destination::factory()->create(['name' => ['en' => 'Cairo']]);
    $this->luxor = Destination::factory()->create(['name' => ['en' => 'Luxor']]);
    $this->pyramids = Trip::factory()->create(['destination_id' => $this->cairo->id, 'title' => ['en' => 'Pyramids tour']]);
    $this->karnak = Trip::factory()->create(['destination_id' => $this->luxor->id, 'title' => ['en' => 'Karnak tour']]);
});

function packagePayload(array $overrides = []): array
{
    return [
        'title' => ['en' => 'Classic Egypt', 'it' => 'Egitto classico'],
        'summary' => ['en' => 'Cairo and Luxor'],
        'highlights' => ['en' => "Pyramids\nKarnak"],
        'days' => 5,
        'nights' => 4,
        'status' => 'published',
        'private_enabled' => '1',
        'group_enabled' => '1',
        'min_persons' => 1,
        'max_persons' => 10,
        'price_mode' => 'fixed',
        'base_price' => 990,
        'base_child_price' => 690,
        'deposit_percent' => 30,
        'stages' => [
            ['destination_id' => test()->cairo->id, 'days' => 3, 'nights' => 3, 'accommodation' => ['en' => 'Cairo hotel'], 'trips' => [['day_number' => 2, 'trip_id' => test()->pyramids->id]]],
            ['destination_id' => test()->luxor->id, 'days' => 2, 'nights' => 1, 'trips' => [['day_number' => 1, 'trip_id' => test()->karnak->id], ['day_number' => 1, 'trip_id' => '']]],
        ],
        'transfers' => [
            ['day_number' => 1, 'time' => '10:00', 'from_place' => 'Cairo airport', 'to_place' => 'Cairo hotel', 'vehicle_type' => 'minivan'],
            ['day_number' => 4, 'time' => '', 'from_place' => 'Cairo hotel', 'to_place' => 'Luxor hotel', 'vehicle_type' => ''],
        ],
        ...$overrides,
    ];
}

test('the package list and builder render', function () {
    $package = Package::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.packages.index'))->assertOk()->assertSee('New package');
    $this->actingAs($this->admin)->get(route('admin.packages.create'))->assertOk()->assertSee('Included transfers');
    $this->actingAs($this->admin)->get(route('admin.packages.edit', $package))->assertOk()->assertSee('Price list');
    $this->actingAs($this->admin)->getJson(route('admin.packages.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
});

test('a package is created with stages, trips per day, included transfers and a from price', function () {
    $this->actingAs($this->admin)->post(route('admin.packages.store'), packagePayload())->assertSessionHasNoErrors();

    $package = Package::query()->where('slug', 'classic-egypt')->sole();

    expect($package->destination_id)->toBe($this->cairo->id)
        ->and($package->highlights)->toBe(['Pyramids', 'Karnak'])
        ->and($package->stages)->toHaveCount(2)
        ->and($package->stages[1]->stageTrips)->toHaveCount(1)
        ->and($package->includedTransfers)->toHaveCount(2)
        ->and($package->includedTransfers[1]->package_stage_id)->toBe($package->stages[1]->id)
        ->and((float) $package->from_price)->toBe(990.0);
});

test('stage days must add up to the package duration and trips must fit their stage', function () {
    $payload = packagePayload(['days' => 6]);
    $payload['stages'][0]['trips'][0]['day_number'] = 9;

    $this->actingAs($this->admin)->post(route('admin.packages.store'), $payload)->assertSessionHasErrors(['stages']);
    $this->actingAs($this->admin)->post(route('admin.packages.store'), packagePayload(['days' => 5, 'stages' => [['destination_id' => $this->cairo->id, 'days' => 5, 'trips' => [['day_number' => 7, 'trip_id' => $this->pyramids->id]]]]]))
        ->assertSessionHasErrors(['stages.0.trips.0.day_number']);
});

test('a package is updated, priced from its trips and gets a season price list', function () {
    $this->actingAs($this->admin)->post(route('admin.packages.store'), packagePayload());
    $package = Package::query()->sole();
    $this->pyramids->update(['from_price' => 60]);
    $this->karnak->update(['from_price' => 40]);

    $this->actingAs($this->admin)->put(route('admin.packages.update', $package), packagePayload(['price_mode' => 'computed', 'base_price' => '', 'discount_percent' => 10, 'transfers' => []]))
        ->assertSessionHasNoErrors();

    $package->refresh();
    expect($package->price_mode)->toBe(PackagePriceMode::Computed)
        ->and($package->includedTransfers)->toHaveCount(0)
        ->and((float) $package->from_price)->toBe(90.0);

    $package->update(['price_mode' => PackagePriceMode::Fixed, 'base_price' => 990]);
    $this->actingAs($this->admin)->put(route('admin.packages.seasons.update', $package), ['seasons' => [
        ['name' => ['en' => 'Summer'], 'starts_on' => '2027-06-01', 'ends_on' => '2027-08-31', 'is_active' => 1, 'adult_price' => 900, 'child_price' => 600],
    ]])->assertRedirect(route('admin.packages.edit', $package).'#tab-prices');

    expect(SeasonPrice::query()->where('priceable_type', 'package')->count())->toBe(1);
});

test('editors cannot open the package builder', function () {
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.packages.create'))->assertForbidden();
});
