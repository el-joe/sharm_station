<?php

namespace Database\Seeders;

use App\Enums\AddonPricingBasis;
use App\Enums\BookingType;
use App\Enums\DiscountType;
use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use App\Models\Addon;
use App\Models\Destination;
use App\Models\Trip;
use App\Services\SeasonPriceService;
use Database\Seeders\Concerns\SeedsSeasons;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    use SeedsSeasons;

    /**
     * @var array<string, Addon>
     */
    private array $addons = [];

    public function run(): void
    {
        $this->seedAddons();

        $this->buggySafari();
        $this->rasMohammedSnorkelling();
        $this->pyramidsDayTour();
        $this->luxorMultiDay();
        $this->luxorDayTour();
        $this->nileCruise();
        $this->feluccaRide();
        $this->giftunIsland();
        $this->wadiElGamal();
    }

    private function seedAddons(): void
    {
        $definitions = [
            'balloon' => [['Hot-air balloon at sunrise', 'Mongolfiera all\'alba'], 95, 85, AddonPricingBasis::PerPerson, false, 20],
            'lunch' => [['Lunch upgrade', 'Pranzo superiore'], 12, null, AddonPricingBasis::PerPerson, false, 50],
            'guide' => [['Private Italian-speaking guide', 'Guida privata in italiano'], 40, 34, AddonPricingBasis::PerBooking, false, 1],
            'photographer' => [['Photographer', 'Fotografo'], 60, null, AddonPricingBasis::PerBooking, false, 1],
            'gopro' => [['GoPro camera rental', 'Noleggio GoPro'], 10, null, AddonPricingBasis::PerUnit, false, 10],
            'park_fee' => [['Marine park fee', 'Tassa parco marino'], 5, null, AddonPricingBasis::PerPerson, true, 50],
        ];

        foreach ($definitions as $key => [$name, $price, , $basis, $required, $maxQuantity]) {
            $this->addons[$key] = Addon::query()->create([
                'name' => ['en' => $name[0], 'it' => $name[1]],
                'description' => ['en' => $name[0].' added to your booking.', 'it' => $name[1].' aggiunto alla prenotazione.'],
                'image' => '/images/placeholders/addon-'.$key.'.jpg',
                'price' => $price,
                'pricing_basis' => $basis,
                'required' => $required,
                'max_quantity' => $maxQuantity,
                'booking_types' => [BookingType::Private->value, BookingType::Group->value],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function trip(string $destinationSlug, array $attributes): Trip
    {
        $destination = Destination::query()->where('slug', $destinationSlug)->firstOrFail();

        return Trip::query()->create([
            'destination_id' => $destination->id,
            'status' => PublishStatus::Published,
            'deposit_percent' => 30,
            'free_cancellation_hours' => 24,
            ...$attributes,
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string|null, 2: string, 3: string, 4?: string}>  $items  [from, to, en, it, place]
     */
    private function day(Trip $trip, int $number, string $titleEn, string $titleIt, array $items, ?string $descriptionEn = null, ?string $descriptionIt = null): void
    {
        $day = $trip->tripDays()->create([
            'day_number' => $number,
            'title' => ['en' => $titleEn, 'it' => $titleIt],
            'description' => $descriptionEn ? ['en' => $descriptionEn, 'it' => $descriptionIt ?? $descriptionEn] : null,
        ]);

        foreach ($items as $sort => $item) {
            $day->items()->create([
                'time_from' => $item[0],
                'time_to' => $item[1],
                'title' => ['en' => $item[2], 'it' => $item[3]],
                'place' => $item[4] ?? null,
                'sort' => $sort,
            ]);
        }
    }

    /**
     * @param  list<int>|null  $groupWeekdays
     */
    private function rules(Trip $trip, ?array $groupWeekdays, int $leadTimeHours = 24, array $blackoutDates = []): void
    {
        if ($trip->private_enabled) {
            $trip->availabilityRules()->create([
                'booking_type' => BookingType::Private,
                'lead_time_hours' => $leadTimeHours,
                'blackout_dates' => $blackoutDates,
            ]);
        }

        if ($trip->group_enabled) {
            $trip->availabilityRules()->create([
                'booking_type' => BookingType::Group,
                'weekdays' => $groupWeekdays ?? [1, 2, 3, 4, 5, 6, 7],
                'dates' => [],
                'blackout_dates' => $blackoutDates,
                'lead_time_hours' => $leadTimeHours,
            ]);
        }
    }

    private function media(Trip $trip, int $first, int $count = 3): void
    {
        for ($i = 0; $i < $count; $i++) {
            $trip->media()->create([
                'path' => '/images/placeholders/trip-'.($first + $i).'.jpg',
                'alt' => ['en' => $trip->getTranslations('title')['en'].' photo '.($i + 1), 'it' => $trip->getTranslations('title')['it'].' foto '.($i + 1)],
                'sort' => $i,
            ]);
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function attachAddons(Trip $trip, array $keys): void
    {
        foreach ($keys as $sort => $key) {
            $trip->addons()->attach($this->addons[$key]->id, ['sort' => $sort]);
        }
    }

    private function childBands(Trip $trip): void
    {
        $policy = $trip->childPolicy()->create(['children_allowed' => true, 'adult_from_age' => 12]);
        $policy->bands()->createMany([
            ['min_age' => 0, 'max_age' => 3, 'discount_type' => DiscountType::Free, 'discount_value' => null, 'sort' => 0],
            ['min_age' => 4, 'max_age' => 11, 'discount_type' => DiscountType::Percent, 'discount_value' => 50, 'sort' => 1],
        ]);
    }

    private function buggySafari(): void
    {
        $trip = $this->trip('sharm-el-sheikh', [
            'type' => TripType::SingleDay,
            'category' => 'safari',
            'title' => ['en' => 'Desert buggy safari', 'it' => 'Safari in buggy nel deserto'],
            'slug' => 'desert-buggy-safari',
            'summary' => ['en' => 'Drive your own buggy across the Sinai desert to a Bedouin village at sunset.', 'it' => 'Guida il tuo buggy nel deserto del Sinai fino a un villaggio beduino al tramonto.'],
            'description' => ['en' => 'Choose a single, double or family buggy and follow our guides along sandy tracks and canyons. Tea and bread at a Bedouin camp, stargazing and hotel transfers included.', 'it' => 'Scegli un buggy singolo, doppio o familiare e segui le nostre guide tra piste sabbiose e canyon. Tè e pane in un campo beduino, osservazione delle stelle e transfer dall\'hotel inclusi.'],
            'duration_hours' => 4.5,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerUnit,
            'min_persons' => 1,
            'max_persons' => null,
            'highlights' => ['en' => ['Drive your own buggy', 'Sunset in the Sinai desert', 'Bedouin tea and bread'], 'it' => ['Guida il tuo buggy', 'Tramonto nel deserto del Sinai', 'Tè e pane beduino']],
            'included' => ['en' => ['Hotel pick-up and drop-off', 'Helmet and scarf', 'Bedouin tea'], 'it' => ['Prelievo e rientro in hotel', 'Casco e foulard', 'Tè beduino']],
            'excluded' => ['en' => ['Tips', 'Photos'], 'it' => ['Mance', 'Foto']],
            'meeting_point' => ['en' => 'Hotel lobby pick-up in Sharm El Sheikh', 'it' => 'Prelievo nella hall dell\'hotel a Sharm El Sheikh'],
            'lat' => 27.9158,
            'lng' => 34.3299,
            'cover_image' => '/images/placeholders/trip-1.jpg',
            'featured' => true,
            'sort' => 1,
        ]);

        $single = $trip->unitOptions()->create(['name' => ['en' => 'Single buggy', 'it' => 'Buggy singolo'], 'seats' => 1, 'price' => 35, 'max_quantity' => 10, 'children_allowed' => false, 'sort' => 0]);
        $double = $trip->unitOptions()->create(['name' => ['en' => 'Double buggy', 'it' => 'Buggy doppio'], 'seats' => 2, 'price' => 50, 'max_quantity' => 10, 'children_allowed' => true, 'sort' => 1]);
        $family = $trip->unitOptions()->create(['name' => ['en' => 'Family buggy', 'it' => 'Buggy familiare'], 'seats' => 4, 'price' => 90, 'max_quantity' => 5, 'children_allowed' => true, 'sort' => 2]);

        $this->rules($trip, null, 12);
        $this->day($trip, 1, 'Desert buggy safari', 'Safari in buggy', [
            ['14:00', '14:45', 'Hotel pick-up', 'Prelievo in hotel'],
            ['14:45', '15:00', 'Safety briefing and helmets', 'Briefing di sicurezza e caschi', 'Buggy camp'],
            ['15:00', '17:00', 'Buggy ride through the canyons', 'Giro in buggy tra i canyon'],
            ['17:00', '18:00', 'Bedouin village, tea and sunset', 'Villaggio beduino, tè e tramonto'],
            ['18:30', null, 'Return to the hotel', 'Rientro in hotel'],
        ]);
        $this->media($trip, 1);
        $this->attachAddons($trip, ['gopro', 'photographer']);

        $this->seedSeasons($trip, [
            'low' => ['unit_prices' => [$single->id => 30, $double->id => 45, $family->id => 80]],
            'high' => ['unit_prices' => [$single->id => 40, $double->id => 58, $family->id => 100]],
            'christmas' => ['unit_prices' => [$single->id => 45, $double->id => 65, $family->id => 110]],
        ]);
    }

    private function rasMohammedSnorkelling(): void
    {
        $trip = $this->trip('sharm-el-sheikh', [
            'type' => TripType::SingleDay,
            'category' => 'marine',
            'title' => ['en' => 'Ras Mohammed snorkelling', 'it' => 'Snorkeling a Ras Mohammed'],
            'slug' => 'ras-mohammed-snorkelling',
            'summary' => ['en' => 'A full day by boat in the Ras Mohammed national park with two snorkelling stops and lunch on board.', 'it' => 'Una giornata in barca nel parco nazionale di Ras Mohammed con due soste di snorkeling e pranzo a bordo.'],
            'description' => ['en' => 'Board our boat in Sharm marina and sail to the reefs of Ras Mohammed: Shark & Yolanda reef, the Magic Lake and the coral garden. Snorkelling gear, lunch and soft drinks included.', 'it' => 'Imbarco al porto di Sharm e navigazione verso i reef di Ras Mohammed: Shark & Yolanda, il Lago Magico e il giardino di corallo. Attrezzatura, pranzo e bibite inclusi.'],
            'duration_hours' => 8,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 1,
            'max_persons' => 12,
            'base_price' => 25,
            'base_child_price' => 15,
            'highlights' => ['en' => ['Two snorkelling stops', 'Lunch on board', 'Marine biologist guide'], 'it' => ['Due soste di snorkeling', 'Pranzo a bordo', 'Guida biologo marino']],
            'included' => ['en' => ['Hotel transfers', 'Mask and fins', 'Lunch and soft drinks'], 'it' => ['Transfer dall\'hotel', 'Maschera e pinne', 'Pranzo e bibite']],
            'excluded' => ['en' => ['Diving', 'Tips'], 'it' => ['Immersioni', 'Mance']],
            'meeting_point' => ['en' => 'Sharm El Sheikh marina, pier 3', 'it' => 'Porto di Sharm El Sheikh, molo 3'],
            'lat' => 27.8597,
            'lng' => 34.2950,
            'cover_image' => '/images/placeholders/trip-4.jpg',
            'featured' => true,
            'sort' => 2,
        ]);

        $tiers = collect([
            [1, 2, 70, 60], [3, 4, 55, 47], [5, 8, 45, null], [9, 12, 40, null],
        ])->map(fn (array $tier) => $trip->privateTiers()->create(['min_people' => $tier[0], 'max_people' => $tier[1], 'price_per_person' => $tier[2]]));

        $this->childBands($trip);
        $this->rules($trip, null, 24, ['2026-12-25']);
        $this->day($trip, 1, 'Ras Mohammed by boat', 'Ras Mohammed in barca', [
            ['07:30', '08:15', 'Hotel pick-up', 'Prelievo in hotel'],
            ['08:30', '10:00', 'Sail to Ras Mohammed', 'Navigazione verso Ras Mohammed', 'Sharm marina'],
            ['10:00', '11:30', 'Snorkelling at Shark & Yolanda reef', 'Snorkeling a Shark & Yolanda', 'Ras Mohammed'],
            ['12:30', '13:30', 'Lunch on board', 'Pranzo a bordo'],
            ['13:30', '15:00', 'Coral garden snorkelling', 'Snorkeling nel giardino di corallo'],
            ['16:30', null, 'Return to the hotel', 'Rientro in hotel'],
        ]);
        $this->media($trip, 4);
        $this->attachAddons($trip, ['park_fee', 'lunch', 'photographer']);

        $tierIds = $tiers->pluck('id')->all();
        $this->seedSeasons($trip, [
            'low' => ['adult_price' => 22, 'child_price' => 13],
            'summer' => ['adult_price' => 28, 'child_price' => 16],
            'high' => ['adult_price' => 30, 'child_price' => 18, 'tier_prices' => array_combine($tierIds, [80, 62, 50, 45])],
            'christmas' => ['adult_price' => 35, 'child_price' => 20, 'tier_prices' => array_combine($tierIds, [90, 70, 58, 50])],
        ]);
    }

    private function pyramidsDayTour(): void
    {
        $trip = $this->trip('cairo', [
            'type' => TripType::SingleDay,
            'category' => 'cultural',
            'title' => ['en' => 'Pyramids & Egyptian Museum day tour', 'it' => 'Piramidi e Museo Egizio in un giorno'],
            'slug' => 'pyramids-egyptian-museum-day-tour',
            'summary' => ['en' => 'The Giza pyramids, the Sphinx and the treasures of Tutankhamun with an Egyptologist guide.', 'it' => 'Le piramidi di Giza, la Sfinge e i tesori di Tutankhamon con una guida egittologa.'],
            'description' => ['en' => 'A full day in Cairo: the Great Pyramid of Khufu, the panoramic plateau, the Sphinx and the valley temple, lunch with a pyramid view and the Egyptian Museum.', 'it' => 'Una giornata intera al Cairo: la Grande Piramide di Cheope, il belvedere, la Sfinge e il tempio a valle, pranzo con vista sulle piramidi e il Museo Egizio.'],
            'duration_hours' => 9,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 1,
            'max_persons' => 15,
            'base_price' => 65,
            'base_child_price' => 40,
            'highlights' => ['en' => ['Great Pyramid and Sphinx', 'Tutankhamun treasures', 'Egyptologist guide'], 'it' => ['Grande Piramide e Sfinge', 'Tesori di Tutankhamon', 'Guida egittologa']],
            'included' => ['en' => ['Air-conditioned transport', 'Entrance fees', 'Lunch'], 'it' => ['Trasporto climatizzato', 'Biglietti d\'ingresso', 'Pranzo']],
            'excluded' => ['en' => ['Entry inside the Great Pyramid', 'Tips'], 'it' => ['Ingresso nella Grande Piramide', 'Mance']],
            'meeting_point' => ['en' => 'Hotel pick-up in Cairo or Giza', 'it' => 'Prelievo in hotel al Cairo o a Giza'],
            'lat' => 29.9792,
            'lng' => 31.1342,
            'cover_image' => '/images/placeholders/trip-7.jpg',
            'featured' => true,
            'sort' => 3,
        ]);

        $tiers = collect([[1, 1, 120], [2, 2, 85], [3, 4, 70], [5, 8, 60], [9, null, 52]])
            ->map(fn (array $tier) => $trip->privateTiers()->create(['min_people' => $tier[0], 'max_people' => $tier[1], 'price_per_person' => $tier[2]]));

        $this->childBands($trip);
        $this->rules($trip, [1, 3, 5, 6], 48);
        $this->day($trip, 1, 'Giza and the Egyptian Museum', 'Giza e il Museo Egizio', [
            ['07:30', null, 'Hotel pick-up', 'Prelievo in hotel'],
            ['08:30', '11:30', 'Pyramids of Giza and the Sphinx', 'Piramidi di Giza e Sfinge', 'Giza plateau'],
            ['12:00', '13:00', 'Lunch with pyramid view', 'Pranzo con vista sulle piramidi'],
            ['13:30', '16:30', 'Egyptian Museum', 'Museo Egizio', 'Tahrir Square'],
            ['17:00', null, 'Return to the hotel', 'Rientro in hotel'],
        ]);
        $this->media($trip, 7);
        $this->attachAddons($trip, ['guide', 'lunch']);

        $tierIds = $tiers->pluck('id')->all();
        $this->seedSeasons($trip, [
            'high' => ['adult_price' => 72, 'child_price' => 45, 'tier_prices' => array_combine($tierIds, [130, 95, 78, 66, 58])],
            'christmas' => ['adult_price' => 79, 'child_price' => 49, 'tier_prices' => array_combine($tierIds, [140, 105, 85, 72, 63])],
        ]);
    }

    private function luxorMultiDay(): void
    {
        $trip = $this->trip('luxor', [
            'type' => TripType::MultiDay,
            'category' => 'cultural',
            'title' => ['en' => 'Luxor & Valley of the Kings, 3 days', 'it' => 'Luxor e Valle dei Re, 3 giorni'],
            'slug' => 'luxor-valley-of-the-kings-3-days',
            'summary' => ['en' => 'Three days on both banks of the Nile: Karnak, Luxor temple, the Valley of the Kings and Hatshepsut.', 'it' => 'Tre giorni sulle due sponde del Nilo: Karnak, il tempio di Luxor, la Valle dei Re e Hatshepsut.'],
            'description' => ['en' => 'Guided programme with two nights in Luxor. Day 1 East Bank, day 2 West Bank, day 3 free morning and return.', 'it' => 'Programma guidato con due notti a Luxor. Giorno 1 sponda est, giorno 2 sponda ovest, giorno 3 mattina libera e rientro.'],
            'days' => 3,
            'nights' => 2,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 2,
            'max_persons' => 10,
            'base_price' => 290,
            'base_child_price' => 190,
            'deposit_percent' => 25,
            'free_cancellation_hours' => 72,
            'highlights' => ['en' => ['Karnak and Luxor temples', 'Valley of the Kings', 'Temple of Hatshepsut'], 'it' => ['Templi di Karnak e Luxor', 'Valle dei Re', 'Tempio di Hatshepsut']],
            'included' => ['en' => ['2 nights in a 4-star hotel', 'Breakfast and dinner', 'Egyptologist guide', 'Entrance fees'], 'it' => ['2 notti in hotel 4 stelle', 'Colazione e cena', 'Guida egittologa', 'Biglietti d\'ingresso']],
            'excluded' => ['en' => ['Flights', 'Tips'], 'it' => ['Voli', 'Mance']],
            'meeting_point' => ['en' => 'Luxor airport or your Luxor hotel', 'it' => 'Aeroporto di Luxor o il tuo hotel a Luxor'],
            'cover_image' => '/images/placeholders/trip-10.jpg',
            'featured' => true,
            'sort' => 4,
        ]);

        $tiers = collect([[2, 2, 340], [3, 4, 310], [5, 10, 280]])
            ->map(fn (array $tier) => $trip->privateTiers()->create(['min_people' => $tier[0], 'max_people' => $tier[1], 'price_per_person' => $tier[2]]));

        $this->childBands($trip);
        $this->rules($trip, [2, 5], 72);
        $this->day($trip, 1, 'East Bank temples', 'Templi della sponda est', [
            ['10:00', '11:00', 'Arrival and hotel check-in', 'Arrivo e check-in in hotel'],
            ['14:00', '17:00', 'Karnak temple complex', 'Complesso di Karnak', 'Karnak'],
            ['18:00', '19:30', 'Luxor temple at dusk', 'Tempio di Luxor al tramonto', 'Luxor'],
        ], 'Overnight in Luxor.', 'Pernottamento a Luxor.');
        $this->day($trip, 2, 'West Bank and the Valley of the Kings', 'Sponda ovest e Valle dei Re', [
            ['06:30', '10:00', 'Valley of the Kings, three tombs', 'Valle dei Re, tre tombe', 'Valley of the Kings'],
            ['10:30', '12:00', 'Temple of Hatshepsut', 'Tempio di Hatshepsut', 'Deir el-Bahari'],
            ['12:15', '12:45', 'Colossi of Memnon', 'Colossi di Memnone'],
        ], 'Overnight in Luxor.', 'Pernottamento a Luxor.');
        $this->day($trip, 3, 'Free morning and departure', 'Mattina libera e partenza', [
            ['09:00', '12:00', 'Free time or optional balloon flight', 'Tempo libero o volo in mongolfiera facoltativo'],
            ['13:00', null, 'Transfer to the airport', 'Transfer in aeroporto'],
        ]);
        $this->media($trip, 10);
        $this->attachAddons($trip, ['balloon', 'guide']);

        $tierIds = $tiers->pluck('id')->all();
        $this->seedSeasons($trip, [
            'summer' => ['adult_price' => 250, 'child_price' => 160, 'tier_prices' => array_combine($tierIds, [300, 270, 245])],
            'high' => ['adult_price' => 320, 'child_price' => 210, 'tier_prices' => array_combine($tierIds, [370, 340, 310])],
            'christmas' => ['adult_price' => 360, 'child_price' => 230, 'tier_prices' => array_combine($tierIds, [410, 380, 350])],
        ]);
    }

    private function luxorDayTour(): void
    {
        $trip = $this->trip('luxor', [
            'type' => TripType::SingleDay,
            'category' => 'cultural',
            'title' => ['en' => 'Luxor East & West Bank day tour', 'it' => 'Luxor sponda est e ovest in un giorno'],
            'slug' => 'luxor-east-west-bank-day-tour',
            'summary' => ['en' => 'Karnak, the Valley of the Kings and Hatshepsut in one guided day.', 'it' => 'Karnak, la Valle dei Re e Hatshepsut in una giornata guidata.'],
            'duration_hours' => 10,
            'private_enabled' => true,
            'group_enabled' => false,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 1,
            'max_persons' => 8,
            'base_price' => null,
            'cover_image' => '/images/placeholders/trip-13.jpg',
            'sort' => 5,
        ]);

        collect([[1, 2, 85], [3, 4, 70], [5, 8, 58]])
            ->each(fn (array $tier) => $trip->privateTiers()->create(['min_people' => $tier[0], 'max_people' => $tier[1], 'price_per_person' => $tier[2]]));

        $this->rules($trip, null, 24);
        $this->day($trip, 1, 'Luxor in a day', 'Luxor in un giorno', [
            ['07:00', '10:00', 'Valley of the Kings', 'Valle dei Re'],
            ['10:30', '12:00', 'Temple of Hatshepsut', 'Tempio di Hatshepsut'],
            ['14:00', '16:30', 'Karnak temple', 'Tempio di Karnak'],
        ]);
        $this->attachAddons($trip, ['balloon', 'lunch']);
        app(SeasonPriceService::class)->refreshFromPrice($trip);
    }

    private function nileCruise(): void
    {
        $trip = $this->trip('luxor', [
            'type' => TripType::Nile,
            'category' => 'cruise',
            'title' => ['en' => 'M/S Nile Sun 4 nights Luxor–Aswan', 'it' => 'M/S Nile Sun 4 notti Luxor–Assuan'],
            'slug' => 'ms-nile-sun-4-nights-luxor-aswan',
            'summary' => ['en' => 'Five-day full-board cruise from Luxor to Aswan with guided visits to Edfu, Kom Ombo and Philae.', 'it' => 'Crociera di cinque giorni in pensione completa da Luxor ad Assuan con visite guidate a Edfu, Kom Ombo e Philae.'],
            'description' => ['en' => 'Sail the Nile aboard the 5-star M/S Nile Sun. Cabins with panoramic windows, pool on the sun deck and Egyptologist guides for every visit.', 'it' => 'Navigazione sul Nilo a bordo della M/S Nile Sun 5 stelle. Cabine con finestre panoramiche, piscina sul ponte sole e guide egittologhe per ogni visita.'],
            'days' => 5,
            'nights' => 4,
            'private_enabled' => false,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 1,
            'max_persons' => 8,
            'base_price' => 480,
            'base_child_price' => 290,
            'deposit_percent' => 30,
            'free_cancellation_hours' => 168,
            'vessel_name' => 'M/S Nile Sun',
            'itinerary_from' => 'Luxor',
            'itinerary_to' => 'Aswan',
            'vessel_facilities' => ['pool', 'restaurant', 'sun_terrace', 'wifi', 'bar'],
            'meal_type' => 'full_board',
            'highlights' => ['en' => ['Full board on a 5-star ship', 'Edfu and Kom Ombo temples', 'Philae and the High Dam'], 'it' => ['Pensione completa su nave 5 stelle', 'Templi di Edfu e Kom Ombo', 'Philae e la Grande Diga']],
            'included' => ['en' => ['4 nights on board', 'Full board', 'Guided visits'], 'it' => ['4 notti a bordo', 'Pensione completa', 'Visite guidate']],
            'excluded' => ['en' => ['Drinks', 'Abu Simbel excursion'], 'it' => ['Bevande', 'Escursione ad Abu Simbel']],
            'meeting_point' => ['en' => 'Luxor cruise dock, Corniche El Nile', 'it' => 'Molo crociere di Luxor, Corniche El Nile'],
            'cover_image' => '/images/placeholders/trip-16.jpg',
            'featured' => true,
            'sort' => 6,
        ]);

        $this->childBands($trip);
        $this->rules($trip, [1, 5], 72);
        $this->day($trip, 1, 'Embarkation in Luxor', 'Imbarco a Luxor', [
            ['12:00', null, 'Embarkation and lunch', 'Imbarco e pranzo'],
            ['15:00', '18:00', 'Karnak and Luxor temples', 'Templi di Karnak e Luxor'],
        ]);
        $this->day($trip, 2, 'West Bank and sailing to Edfu', 'Sponda ovest e navigazione verso Edfu', [
            ['06:00', '11:00', 'Valley of the Kings and Hatshepsut', 'Valle dei Re e Hatshepsut'],
            ['13:00', null, 'Sailing to Esna and Edfu', 'Navigazione verso Esna ed Edfu'],
        ]);
        $this->day($trip, 3, 'Edfu and Kom Ombo', 'Edfu e Kom Ombo', [
            ['07:00', '09:00', 'Temple of Horus at Edfu', 'Tempio di Horus a Edfu'],
            ['17:00', '18:30', 'Temple of Kom Ombo', 'Tempio di Kom Ombo'],
        ]);
        $this->day($trip, 4, 'Aswan', 'Assuan', [
            ['08:00', '12:00', 'High Dam and Philae temple', 'Grande Diga e tempio di Philae'],
            ['16:00', '17:30', 'Felucca around Elephantine island', 'Feluca intorno all\'isola Elefantina'],
        ]);
        $this->day($trip, 5, 'Disembarkation', 'Sbarco', [
            ['08:00', '09:00', 'Breakfast and disembarkation', 'Colazione e sbarco'],
        ]);
        $this->media($trip, 16);
        $this->attachAddons($trip, ['balloon', 'photographer']);

        $this->seedSeasons($trip, [
            'low' => ['adult_price' => 430, 'child_price' => 260],
            'spring' => ['adult_price' => 460, 'child_price' => 280],
            'summer' => ['adult_price' => 390, 'child_price' => 230],
            'high' => ['adult_price' => 540, 'child_price' => 320],
            'christmas' => ['adult_price' => 620, 'child_price' => 370],
        ]);
    }

    private function feluccaRide(): void
    {
        $trip = $this->trip('aswan', [
            'type' => TripType::Nile,
            'category' => 'cruise',
            'title' => ['en' => 'Sunset felucca ride in Aswan', 'it' => 'Giro in feluca al tramonto ad Assuan'],
            'slug' => 'sunset-felucca-ride-aswan',
            'summary' => ['en' => 'A private felucca around Elephantine and Kitchener\'s islands at sunset.', 'it' => 'Una feluca privata intorno all\'isola Elefantina e all\'isola di Kitchener al tramonto.'],
            'duration_hours' => 2,
            'days' => 1,
            'nights' => 0,
            'private_enabled' => true,
            'group_enabled' => false,
            'pricing_mode' => PricingMode::PerUnit,
            'min_persons' => 1,
            'vessel_name' => 'Traditional felucca',
            'itinerary_from' => 'Aswan Corniche',
            'itinerary_to' => 'Elephantine island',
            'vessel_facilities' => ['shade', 'cushions'],
            'meal_type' => 'tea',
            'free_cancellation_hours' => 12,
            'cover_image' => '/images/placeholders/trip-19.jpg',
            'sort' => 7,
        ]);

        $trip->unitOptions()->create(['name' => ['en' => 'Felucca, up to 6 guests', 'it' => 'Feluca, fino a 6 ospiti'], 'seats' => 6, 'price' => 45, 'max_quantity' => 2, 'children_allowed' => true, 'sort' => 0]);
        $trip->unitOptions()->create(['name' => ['en' => 'Large felucca, up to 12 guests', 'it' => 'Feluca grande, fino a 12 ospiti'], 'seats' => 12, 'price' => 80, 'max_quantity' => 2, 'children_allowed' => true, 'sort' => 1]);

        $this->rules($trip, null, 12);
        $this->day($trip, 1, 'Sunset on the Nile', 'Tramonto sul Nilo', [
            ['16:30', '18:30', 'Felucca sailing with Nubian tea', 'Navigazione in feluca con tè nubiano', 'Aswan Corniche'],
        ]);
        $this->media($trip, 19, 2);
        app(SeasonPriceService::class)->refreshFromPrice($trip);
    }

    private function giftunIsland(): void
    {
        $trip = $this->trip('hurghada', [
            'type' => TripType::SingleDay,
            'category' => 'marine',
            'title' => ['en' => 'Giftun Island boat trip', 'it' => 'Escursione in barca all\'isola di Giftun'],
            'slug' => 'giftun-island-boat-trip',
            'summary' => ['en' => 'White sand beach, two reef stops and lunch on board from Hurghada.', 'it' => 'Spiaggia bianca, due soste sul reef e pranzo a bordo da Hurghada.'],
            'duration_hours' => 7,
            'private_enabled' => false,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'max_persons' => 20,
            'base_price' => 30,
            'base_child_price' => 18,
            'cover_image' => '/images/placeholders/trip-21.jpg',
            'sort' => 8,
        ]);

        $this->rules($trip, [1, 2, 3, 4, 5, 6, 7], 24);
        $this->day($trip, 1, 'Giftun Island', 'Isola di Giftun', [
            ['08:00', null, 'Hotel pick-up', 'Prelievo in hotel'],
            ['10:00', '13:00', 'Beach time on Giftun', 'Relax in spiaggia a Giftun'],
            ['13:00', '14:00', 'Lunch on board', 'Pranzo a bordo'],
        ]);
        $this->attachAddons($trip, ['park_fee', 'lunch']);
        $this->seedSeasons($trip, [
            'summer' => ['adult_price' => 34, 'child_price' => 20],
            'christmas' => ['adult_price' => 38, 'child_price' => 22],
        ]);
    }

    private function wadiElGamal(): void
    {
        $trip = $this->trip('marsa-alam', [
            'type' => TripType::SingleDay,
            'category' => 'safari',
            'title' => ['en' => 'Wadi El Gamal national park safari', 'it' => 'Safari nel parco nazionale di Wadi El Gamal'],
            'slug' => 'wadi-el-gamal-safari',
            'summary' => ['en' => 'Jeep safari to the acacia valley, gazelles and a Bedouin lunch.', 'it' => 'Safari in jeep nella valle delle acacie, gazzelle e pranzo beduino.'],
            'duration_hours' => 7,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'max_persons' => 12,
            'base_price' => 55,
            'base_child_price' => 30,
            'cover_image' => '/images/placeholders/trip-23.jpg',
            'sort' => 9,
        ]);

        collect([[1, 2, 90], [3, 6, 70], [7, 12, 60]])
            ->each(fn (array $tier) => $trip->privateTiers()->create(['min_people' => $tier[0], 'max_people' => $tier[1], 'price_per_person' => $tier[2]]));

        $this->rules($trip, [2, 4, 6], 24);
        $this->day($trip, 1, 'Wadi El Gamal', 'Wadi El Gamal', [
            ['08:00', null, 'Hotel pick-up', 'Prelievo in hotel'],
            ['09:30', '12:30', 'Jeep safari in the valley', 'Safari in jeep nella valle'],
            ['13:00', '14:00', 'Bedouin lunch', 'Pranzo beduino'],
        ]);
        app(SeasonPriceService::class)->refreshFromPrice($trip);
    }
}
