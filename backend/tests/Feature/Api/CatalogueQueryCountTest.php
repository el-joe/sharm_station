<?php

use App\Enums\PackagePriceMode;
use App\Models\Building;
use App\Models\Destination;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Room;
use App\Models\TransferFixedRoute;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    Sanctum::actingAs(Partner::factory()->create()->user);
});

/**
 * Packages priced from their trips (two stages, one trip each).
 */
function computedPackages(int $count): void
{
    $destination = Destination::factory()->create();

    for ($index = 0; $index < $count; $index++) {
        $package = Package::factory()->create(['destination_id' => $destination->id, 'price_mode' => PackagePriceMode::Computed, 'discount_percent' => 10]);

        foreach ([1, 2] as $position) {
            $stage = $package->stages()->create(['destination_id' => $destination->id, 'position' => $position, 'days' => 2, 'nights' => 1]);
            $stage->stageTrips()->create(['trip_id' => Trip::factory()->create(['base_price' => 50, 'from_price' => 50])->id, 'day_number' => 1]);
        }
    }
}

function catalogueQueries(string $uri): int
{
    test()->getJson($uri)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->getJson($uri)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('the partner package catalogue does not run queries per package or per trip', function () {
    computedPackages(1);
    $one = catalogueQueries('/api/v1/partner/catalogue?service=packages&nationality=IT');

    computedPackages(4);
    $five = catalogueQueries('/api/v1/partner/catalogue?service=packages&nationality=IT');

    expect($five)->toBe($one);
});

test('the public package list does not run queries per package', function () {
    computedPackages(1);
    $one = catalogueQueries('/api/v1/packages');

    computedPackages(4);
    $five = catalogueQueries('/api/v1/packages');

    expect($five)->toBe($one);
});

test('the full partner catalogue (trips, rooms, transfers) does not run queries per product', function () {
    $seed = function (int $count): void {
        for ($index = 0; $index < $count; $index++) {
            Trip::factory()->create(['base_price' => 30]);
            Room::factory()->for(Building::factory()->state(['status' => 'published']))->create(['base_price' => 80]);
            TransferFixedRoute::factory()->create(['price' => 40]);
        }
    };

    $seed(1);
    $one = catalogueQueries('/api/v1/partner/catalogue?nationality=IT&date=2026-12-01');

    $seed(3);
    $four = catalogueQueries('/api/v1/partner/catalogue?nationality=IT&date=2026-12-01');

    expect($four)->toBe($one);
});
