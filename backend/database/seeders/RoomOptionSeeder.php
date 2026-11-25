<?php

namespace Database\Seeders;

use App\Enums\RoomOptionUnitType;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use App\Models\Package;
use App\Models\Room;
use App\Models\RoomOption;
use App\Models\Trip;
use Illuminate\Database\Seeder;

/**
 * Plan v2.4 phase 2 demo data: optional rooms of the multi-day trip, the Nile cruise cabins and the package stages
 * (SRS 7.1), extra beds and cots on family rooms (SRS 6.2). Safe to run more than once.
 */
class RoomOptionSeeder extends Seeder
{
    public function run(): void
    {
        $this->forTrip('luxor-valley-of-the-kings-3-days', [
            ['name' => ['en' => 'Standard double', 'it' => 'Doppia standard'], 'unit_type' => RoomOptionUnitType::Room, 'stars' => 3, 'pricing_mode' => RoomPricingMode::PerRoom, 'price_period' => RoomPricePeriod::PerNight, 'price' => 55, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3],
            ['name' => ['en' => 'Nile view room', 'it' => 'Camera vista Nilo'], 'unit_type' => RoomOptionUnitType::Room, 'stars' => 5, 'pricing_mode' => RoomPricingMode::PerPerson, 'price_period' => RoomPricePeriod::PerNight, 'price' => 45, 'max_adults' => 3, 'max_children' => 2, 'max_total' => 4],
        ]);

        $this->forTrip('ms-nile-sun-4-nights-luxor-aswan', [
            ['name' => ['en' => 'Upper deck cabin upgrade', 'it' => 'Upgrade cabina ponte superiore'], 'unit_type' => RoomOptionUnitType::Cabin, 'stars' => 5, 'pricing_mode' => RoomPricingMode::PerRoom, 'price_period' => RoomPricePeriod::PerStay, 'price' => 120, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3],
            ['name' => ['en' => 'Royal suite', 'it' => 'Suite reale'], 'unit_type' => RoomOptionUnitType::Suite, 'stars' => 5, 'pricing_mode' => RoomPricingMode::PerRoom, 'price_period' => RoomPricePeriod::PerNight, 'price' => 150, 'max_adults' => 2, 'max_children' => 2, 'max_total' => 4],
        ]);

        $package = Package::query()->where('slug', 'classic-egypt-8-days')->with('stages')->first();

        foreach ($package?->stages ?? [] as $stage) {
            if ((int) $stage->nights < 1) {
                continue;
            }

            $this->upsert($package, ['name' => ['en' => 'Superior room upgrade', 'it' => 'Upgrade camera superior'], 'stage_position' => $stage->position, 'unit_type' => RoomOptionUnitType::Room,
                'stars' => 5, 'pricing_mode' => RoomPricingMode::PerRoom, 'price_period' => RoomPricePeriod::PerNight, 'price' => 40, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3]);
        }

        Room::query()->where('max_total', '>=', 3)->where('max_extra_beds', 0)->whereNull('cot_price')
            ->update(['max_extra_beds' => 1, 'extra_bed_price' => 25, 'max_cots' => 1, 'cot_price' => 0]);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function forTrip(string $slug, array $options): void
    {
        $trip = Trip::query()->where('slug', $slug)->first();

        if ($trip === null) {
            return;
        }

        foreach ($options as $sort => $option) {
            $this->upsert($trip, [...$option, 'sort' => $sort]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(Trip|Package $owner, array $attributes): void
    {
        $existing = RoomOption::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('stage_position', $attributes['stage_position'] ?? null)
            ->where('name->en', $attributes['name']['en'])
            ->first();

        if ($existing === null) {
            RoomOption::query()->create([...$attributes, 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'is_active' => true, 'max_quantity' => 10]);
        }
    }
}
