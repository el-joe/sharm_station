<?php

namespace Database\Seeders;

use App\Enums\PricingMode;
use App\Enums\TripType;
use App\Models\Building;
use App\Models\GoogleReview;
use App\Models\Package;
use App\Models\Room;
use App\Models\Trip;
use App\Models\TripTimelineItem;
use Illuminate\Database\Seeder;

/**
 * Practical information and labels added for the site (FAQ, what to bring, guide languages, cancellation text,
 * unit labels, Nile cabins, "transfer included" timeline items, free cancellation on buildings, review countries).
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $unitLabels = [
            'desert-buggy-safari' => ['en' => 'buggy', 'it' => 'buggy'],
            'sunset-felucca-ride-aswan' => ['en' => 'boat', 'it' => 'barca'],
        ];

        foreach (Trip::query()->get() as $trip) {
            $isNile = $trip->type === TripType::Nile;

            $trip->forceFill([
                'unit_label' => $trip->pricing_mode === PricingMode::PerUnit ? ($unitLabels[$trip->slug] ?? ['en' => 'unit', 'it' => 'unità']) : null,
                'guide_languages' => $isNile ? ['en', 'it', 'de'] : ['en', 'it'],
                'what_to_bring' => [
                    'en' => $isNile ? ['Passport', 'Light clothes and a jacket for the evenings', 'Sun cream and hat'] : ['Sun cream and hat', 'Comfortable shoes', 'Cash for tips and drinks'],
                    'it' => $isNile ? ['Passaporto', 'Abiti leggeri e una giacca per la sera', 'Crema solare e cappello'] : ['Crema solare e cappello', 'Scarpe comode', 'Contanti per mance e bevande'],
                ],
                'cancellation_policy' => [
                    'en' => $trip->free_cancellation_hours !== null
                        ? "Free cancellation up to {$trip->free_cancellation_hours} hours before the start. Later cancellations are not refunded."
                        : 'The deposit is not refundable. The balance is refunded if you cancel more than 7 days before the start.',
                    'it' => $trip->free_cancellation_hours !== null
                        ? "Cancellazione gratuita fino a {$trip->free_cancellation_hours} ore prima della partenza. Le cancellazioni successive non sono rimborsate."
                        : 'L\'acconto non è rimborsabile. Il saldo viene rimborsato se annulli più di 7 giorni prima della partenza.',
                ],
                'faq' => [
                    'en' => [
                        ['question' => 'Is the hotel transfer included?', 'answer' => 'Yes, pick-up and drop-off from hotels in the area are included unless stated otherwise.'],
                        ['question' => 'Can children join?', 'answer' => 'Children are welcome; age discounts are applied automatically in the booking widget.'],
                        ['question' => 'How do I pay the balance?', 'answer' => 'You pay the deposit online and the balance on the day, in cash or by transfer.'],
                    ],
                    'it' => [
                        ['question' => 'Il transfer dall\'hotel è incluso?', 'answer' => 'Sì, il prelievo e il rientro dagli hotel della zona sono inclusi salvo diversa indicazione.'],
                        ['question' => 'Possono partecipare i bambini?', 'answer' => 'I bambini sono benvenuti; gli sconti per età sono applicati automaticamente.'],
                        ['question' => 'Come pago il saldo?', 'answer' => 'Paghi l\'acconto online e il saldo il giorno stesso, in contanti o con bonifico.'],
                    ],
                ],
                'cabins' => $trip->slug === 'ms-nile-sun-4-nights-luxor-aswan' ? [
                    ['type' => 'standard', 'label' => ['en' => 'Standard double cabin', 'it' => 'Cabina doppia standard'], 'capacity' => 2, 'stars' => 5],
                    ['type' => 'standard', 'label' => ['en' => 'Triple cabin', 'it' => 'Cabina tripla'], 'capacity' => 3, 'stars' => 5],
                    ['type' => 'suite', 'label' => ['en' => 'Suite with balcony', 'it' => 'Suite con balcone'], 'capacity' => 2, 'stars' => 5],
                ] : null,
            ])->save();
        }

        TripTimelineItem::query()->get()->each(function (TripTimelineItem $item): void {
            $title = strtolower((string) ($item->getTranslations('title')['en'] ?? ''));

            if (str_contains($title, 'pick-up') || str_contains($title, 'return to the hotel') || str_contains($title, 'transfer')) {
                $item->forceFill(['transfer_included' => true])->save();
            }
        });

        Package::query()->get()->each(fn (Package $package) => $package->forceFill([
            'guide_languages' => ['en', 'it'],
            'what_to_bring' => ['en' => ['Passport valid for 6 months', 'Visa on arrival (USD 25)', 'Light clothes and sun protection'], 'it' => ['Passaporto con validità di 6 mesi', 'Visto all\'arrivo (USD 25)', 'Abiti leggeri e protezione solare']],
            'cancellation_policy' => ['en' => 'Free cancellation up to 14 days before departure; 50% refund from 14 to 7 days; no refund later.', 'it' => 'Cancellazione gratuita fino a 14 giorni dalla partenza; rimborso del 50% da 14 a 7 giorni; nessun rimborso dopo.'],
            'faq' => [
                'en' => [['question' => 'Are flights included?', 'answer' => 'International flights are not included; internal transfers between stages are.'], ['question' => 'Can I add extra nights?', 'answer' => 'Yes, ask us after booking and we will quote the extra nights.']],
                'it' => [['question' => 'I voli sono inclusi?', 'answer' => 'I voli internazionali non sono inclusi; i transfer tra le tappe sì.'], ['question' => 'Posso aggiungere notti?', 'answer' => 'Sì, scrivici dopo la prenotazione e ti invieremo un preventivo.']],
            ],
        ])->save());

        Building::query()->get()->each(fn (Building $building, int $index) => $building->forceFill([
            'free_cancellation_hours' => $index === 0 ? 72 : 48,
        ])->save());

        Room::query()->with('building')->get()->each(function (Room $room): void {
            if (blank($room->getTranslations('description'))) {
                $room->forceFill(['description' => [
                    'en' => $room->getTranslations('name')['en'].' at '.$room->building->getTranslations('name')['en'].', with air conditioning, Wi-Fi and a private bathroom.',
                    'it' => ($room->getTranslations('name')['it'] ?? $room->getTranslations('name')['en']).' presso '.($room->building->getTranslations('name')['it'] ?? $room->building->getTranslations('name')['en']).', con aria condizionata, Wi-Fi e bagno privato.',
                ]])->save();
            }
        });

        $countries = ['Italy', 'United Kingdom', 'Germany', 'Egypt', 'France', 'Netherlands'];

        GoogleReview::query()->orderBy('id')->get()->each(fn (GoogleReview $review, int $index) => $review->forceFill([
            'author_country' => $countries[$index % count($countries)],
        ])->save());
    }
}
