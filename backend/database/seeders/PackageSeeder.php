<?php

namespace Database\Seeders;

use App\Enums\PackagePriceMode;
use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\VehicleType;
use App\Models\Destination;
use App\Models\Package;
use App\Models\Trip;
use Database\Seeders\Concerns\SeedsSeasons;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    use SeedsSeasons;

    public function run(): void
    {
        $destinations = Destination::query()->pluck('id', 'slug');
        $trips = Trip::query()->pluck('id', 'slug');

        $package = Package::query()->create([
            'destination_id' => $destinations['cairo'],
            'title' => ['en' => 'Classic Egypt 8 days', 'it' => 'Egitto classico 8 giorni'],
            'slug' => 'classic-egypt-8-days',
            'summary' => ['en' => 'Cairo, Luxor and the Red Sea at Marsa Alam in one programme, with every transfer included.', 'it' => 'Il Cairo, Luxor e il Mar Rosso a Marsa Alam in un unico programma, con tutti i transfer inclusi.'],
            'description' => ['en' => 'Three nights in Cairo with the pyramids, two nights in Luxor for the temples and three nights of sea in Marsa Alam. Airport pick-up, drop-off and transfers between stages are included.', 'it' => 'Tre notti al Cairo con le piramidi, due notti a Luxor per i templi e tre notti di mare a Marsa Alam. Prelievo, rientro in aeroporto e transfer tra le tappe inclusi.'],
            'days' => 8,
            'nights' => 7,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 2,
            'max_persons' => 10,
            'price_mode' => PackagePriceMode::Fixed,
            'base_price' => 990,
            'base_child_price' => 690,
            'deposit_percent' => 30,
            'free_cancellation_hours' => 336,
            'highlights' => ['en' => ['Pyramids of Giza', 'Valley of the Kings', 'Red Sea snorkelling'], 'it' => ['Piramidi di Giza', 'Valle dei Re', 'Snorkeling nel Mar Rosso']],
            'included' => ['en' => ['7 nights in 4-star hotels', 'Airport and inter-stage transfers', 'Guided visits'], 'it' => ['7 notti in hotel 4 stelle', 'Transfer aeroporto e tra le tappe', 'Visite guidate']],
            'excluded' => ['en' => ['International flights', 'Tips'], 'it' => ['Voli internazionali', 'Mance']],
            'cover_image' => '/images/placeholders/package-1.jpg',
            'status' => PublishStatus::Published,
            'featured' => true,
        ]);

        $stages = [
            ['cairo', 3, 3, [[2, 'pyramids-egyptian-museum-day-tour']], 'Cairo Pyramids Hotel 4★', 'Cairo Pyramids Hotel 4★'],
            ['luxor', 2, 2, [[1, 'luxor-east-west-bank-day-tour']], 'Hotel Nile View 4★', 'Hotel Nile View 4★'],
            ['marsa-alam', 3, 2, [[2, 'wadi-el-gamal-safari']], 'Marsa Alam beach resort 4★', 'Resort sul mare a Marsa Alam 4★'],
        ];

        foreach ($stages as $position => [$destination, $days, $nights, $stageTrips, $hotelEn, $hotelIt]) {
            $stage = $package->stages()->create([
                'destination_id' => $destinations[$destination],
                'position' => $position + 1,
                'days' => $days,
                'nights' => $nights,
                'accommodation' => ['en' => $hotelEn, 'it' => $hotelIt],
            ]);

            foreach ($stageTrips as $sort => [$dayNumber, $tripSlug]) {
                $stage->stageTrips()->create(['trip_id' => $trips[$tripSlug], 'day_number' => $dayNumber, 'sort' => $sort]);
            }
        }

        $stageIds = $package->stages()->pluck('id')->all();

        foreach ([
            [$stageIds[0], 'Cairo airport', 'Cairo hotel', 1, '10:00', VehicleType::Minivan],
            [$stageIds[0], 'Cairo hotel', 'Cairo airport (flight to Luxor)', 4, '07:00', VehicleType::Minivan],
            [$stageIds[1], 'Luxor airport', 'Hotel Nile View', 4, '10:30', VehicleType::Minivan],
            [$stageIds[1], 'Hotel Nile View', 'Marsa Alam resort', 6, '08:00', VehicleType::Van],
            [$stageIds[2], 'Marsa Alam resort', 'Marsa Alam airport', 8, '09:00', VehicleType::Minivan],
        ] as [$stageId, $from, $to, $day, $time, $vehicle]) {
            $package->includedTransfers()->create([
                'package_stage_id' => $stageId,
                'from_place' => $from,
                'to_place' => $to,
                'day_number' => $day,
                'time' => $time,
                'vehicle_type' => $vehicle,
            ]);
        }

        $this->seedSeasons($package, [
            'summer' => ['adult_price' => 920, 'child_price' => 640],
            'high' => ['adult_price' => 1090, 'child_price' => 760],
            'christmas' => ['adult_price' => 1190, 'child_price' => 820],
        ]);
    }
}
