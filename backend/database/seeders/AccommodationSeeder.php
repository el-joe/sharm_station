<?php

namespace Database\Seeders;

use App\Enums\BuildingType;
use App\Enums\DiscountType;
use App\Enums\PublishStatus;
use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Models\Building;
use App\Models\Destination;
use Database\Seeders\Concerns\SeedsSeasons;
use Illuminate\Database\Seeder;

class AccommodationSeeder extends Seeder
{
    use SeedsSeasons;

    public const NILE_VIEW_PLACE_ID = 'ChIJnileview-luxor-demo';

    public function run(): void
    {
        $destinations = Destination::query()->pluck('id', 'slug');

        $nileView = Building::query()->create([
            'name' => ['en' => 'Hotel Nile View', 'it' => 'Hotel Nile View'],
            'slug' => 'hotel-nile-view',
            'type' => BuildingType::Hotel,
            'destination_id' => $destinations['luxor'],
            'address' => 'Corniche El Nile, Luxor, Egypt',
            'lat' => 25.6989,
            'lng' => 32.6421,
            'stars' => 4,
            'description' => ['en' => 'A riverside hotel on the Luxor Corniche, ten minutes\' walk from Luxor temple, with a rooftop pool facing the West Bank.', 'it' => 'Un hotel sul lungonilo di Luxor, a dieci minuti a piedi dal tempio di Luxor, con piscina panoramica sulla sponda ovest.'],
            'amenities' => ['wifi', 'pool', 'breakfast', 'reception_24h', 'airport_shuttle', 'restaurant'],
            'cover_image' => '/images/placeholders/building-1.jpg',
            'check_in_from' => '14:00',
            'check_out_until' => '12:00',
            'deposit_percent' => 30,
            'cancellation_policy' => ['en' => 'Free cancellation up to 7 days before check-in; 50% between 7 and 3 days; no refund afterwards.', 'it' => 'Cancellazione gratuita fino a 7 giorni prima del check-in; 50% tra 7 e 3 giorni; nessun rimborso dopo.'],
            'taxes_included' => true,
            'google_place_id' => self::NILE_VIEW_PLACE_ID,
            'phone' => '+20 95 000 0000',
            'email' => 'nileview@sharmstation.test',
            'status' => PublishStatus::Published,
            'featured' => true,
        ]);

        $policy = $nileView->childPolicy()->create(['children_allowed' => true, 'adult_from_age' => 12, 'max_discounted_children' => 2]);
        $policy->bands()->createMany([
            ['min_age' => 0, 'max_age' => 3, 'discount_type' => DiscountType::Free, 'sort' => 0],
            ['min_age' => 4, 'max_age' => 7, 'discount_type' => DiscountType::Percent, 'discount_value' => 50, 'sort' => 1],
            ['min_age' => 8, 'max_age' => 11, 'discount_type' => DiscountType::Percent, 'discount_value' => 25, 'sort' => 2],
        ]);

        $rooms = [
            ['Single room', 'Camera singola', '101', RoomType::Single, 1, 0, 1, '1 single bed', '1 letto singolo', 16, 45, null, 'City view', 'Vista città'],
            ['Standard double room', 'Camera doppia standard', '102', RoomType::Double, 2, 1, 3, '1 queen bed', '1 letto queen', 24, 65, 58, 'Garden view', 'Vista giardino'],
            ['Deluxe double, Nile view', 'Doppia deluxe vista Nilo', '201', RoomType::Double, 2, 1, 3, '1 king bed', '1 letto king', 30, 85, null, 'Nile view', 'Vista Nilo'],
            ['Twin room', 'Camera twin', '202', RoomType::Twin, 2, 1, 3, '2 single beds', '2 letti singoli', 26, 60, null, 'Garden view', 'Vista giardino'],
            ['Triple room', 'Camera tripla', '301', RoomType::Triple, 3, 1, 4, '1 double and 1 single bed', '1 letto matrimoniale e 1 singolo', 34, 95, null, 'Nile view', 'Vista Nilo'],
            ['Family suite', 'Suite familiare', '401', RoomType::Suite, 4, 2, 5, '1 king bed and 2 single beds', '1 letto king e 2 singoli', 48, 140, 125, 'Nile view', 'Vista Nilo'],
        ];

        foreach ($rooms as $sort => [$nameEn, $nameIt, $number, $type, $maxAdults, $maxChildren, $maxTotal, $bedsEn, $bedsIt, $size, $price, , $viewEn, $viewIt]) {
            $room = $nileView->rooms()->create([
                'name' => ['en' => $nameEn, 'it' => $nameIt],
                'number' => $number,
                'room_type' => $type,
                'max_adults' => $maxAdults,
                'max_children' => $maxChildren,
                'max_total' => $maxTotal,
                'beds' => ['en' => $bedsEn, 'it' => $bedsIt],
                'size_m2' => $size,
                'view' => ['en' => $viewEn, 'it' => $viewIt],
                'description' => ['en' => $nameEn.' at Hotel Nile View with air conditioning, minibar and private bathroom.', 'it' => $nameIt.' all\'Hotel Nile View con aria condizionata, minibar e bagno privato.'],
                'amenities' => ['wifi', 'air_conditioning', 'minibar', 'safe'],
                'cover_image' => '/images/placeholders/room-'.($sort + 1).'.jpg',
                'pricing_mode' => RoomPricingMode::PerRoom,
                'base_price' => $price,
                'extra_bed_price' => $maxTotal > $maxAdults ? 15 : null,
                'min_nights' => 1,
                'max_nights' => 21,
                'status' => RoomStatus::Active,
                'sort' => $sort,
            ]);

            $this->seedSeasons($room, [
                'low' => ['price_per_night' => round($price * 0.9, 2)],
                'high' => ['price_per_night' => round($price * 1.2, 2)],
                'christmas' => ['price_per_night' => round($price * 1.4, 2)],
            ]);
        }

        $perPerson = $nileView->rooms()->create([
            'name' => ['en' => 'Superior twin, priced per person', 'it' => 'Twin superior, prezzo a persona'],
            'number' => '203',
            'room_type' => RoomType::Twin,
            'max_adults' => 2,
            'max_children' => 2,
            'max_total' => 3,
            'beds' => ['en' => '2 single beds', 'it' => '2 letti singoli'],
            'size_m2' => 28,
            'amenities' => ['wifi', 'air_conditioning', 'balcony'],
            'cover_image' => '/images/placeholders/room-7.jpg',
            'pricing_mode' => RoomPricingMode::PerPerson,
            'base_price' => 40,
            'min_nights' => 2,
            'max_nights' => 14,
            'status' => RoomStatus::Active,
            'sort' => 10,
        ]);
        $this->seedSeasons($perPerson, ['high' => ['price_per_night' => 48], 'christmas' => ['price_per_night' => 55]]);

        $coralBay = Building::query()->create([
            'name' => ['en' => 'Coral Bay Resort', 'it' => 'Coral Bay Resort'],
            'slug' => 'coral-bay-resort-sharm',
            'type' => BuildingType::Hotel,
            'destination_id' => $destinations['sharm-el-sheikh'],
            'address' => 'Naama Bay, Sharm El Sheikh, Egypt',
            'lat' => 27.9139,
            'lng' => 34.3297,
            'stars' => 5,
            'description' => ['en' => 'Beachfront resort in Naama Bay with a private house reef and three pools.', 'it' => 'Resort sulla spiaggia di Naama Bay con house reef privato e tre piscine.'],
            'amenities' => ['wifi', 'pool', 'beach', 'spa', 'breakfast', 'parking'],
            'cover_image' => '/images/placeholders/building-2.jpg',
            'check_in_from' => '15:00',
            'check_out_until' => '11:00',
            'deposit_percent' => 25,
            'cancellation_policy' => ['en' => 'Free cancellation up to 14 days before check-in.', 'it' => 'Cancellazione gratuita fino a 14 giorni prima del check-in.'],
            'taxes_included' => false,
            'tax_per_night' => 6.5,
            'status' => PublishStatus::Published,
        ]);

        foreach ([
            ['Garden view double', 'Doppia vista giardino', RoomType::Double, 2, 1, 3, 110],
            ['Sea view double', 'Doppia vista mare', RoomType::Double, 2, 1, 3, 145],
            ['Family room', 'Camera familiare', RoomType::Family, 3, 2, 5, 190],
        ] as $sort => [$nameEn, $nameIt, $type, $maxAdults, $maxChildren, $maxTotal, $price]) {
            $room = $coralBay->rooms()->create([
                'name' => ['en' => $nameEn, 'it' => $nameIt],
                'room_type' => $type,
                'max_adults' => $maxAdults,
                'max_children' => $maxChildren,
                'max_total' => $maxTotal,
                'beds' => ['en' => '1 king bed', 'it' => '1 letto king'],
                'size_m2' => 32 + $sort * 8,
                'amenities' => ['wifi', 'air_conditioning', 'balcony'],
                'cover_image' => '/images/placeholders/room-'.(8 + $sort).'.jpg',
                'pricing_mode' => RoomPricingMode::PerRoom,
                'base_price' => $price,
                'min_nights' => 2,
                'max_nights' => 28,
                'status' => RoomStatus::Active,
                'sort' => $sort,
            ]);

            $this->seedSeasons($room, [
                'summer' => ['price_per_night' => round($price * 1.1, 2)],
                'christmas' => ['price_per_night' => round($price * 1.5, 2)],
            ]);
        }
    }
}
