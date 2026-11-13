<?php

namespace Database\Seeders;

use App\Models\GoogleReview;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Sample cached Google reviews (normally filled by reviews:sync-google).
 */
class GoogleReviewSeeder extends Seeder
{
    public function run(): void
    {
        $places = [
            SettingSeeder::BUSINESS_PLACE_ID => [
                'summary' => ['rating' => 4.8, 'total' => 1264, 'maps_url' => 'https://maps.google.com/?cid=1000000000000000001'],
                'reviews' => [
                    ['Francesca M.', 5, 'it', 'Escursione a Ras Mohammed fantastica, guida preparatissima e organizzazione perfetta dal prelievo in hotel al rientro.', 6],
                    ['James W.', 5, 'en', 'The buggy safari was the highlight of our week. Clear briefing, good buggies and a beautiful sunset at the Bedouin camp.', 12],
                    ['Laura B.', 4, 'it', 'Crociera sul Nilo bellissima, cabine pulite e cibo buono. Unico neo la partenza un po\' in ritardo.', 25],
                    ['Ahmed S.', 5, 'en', 'Booked the Pyramids day tour for my parents. The Egyptologist guide was excellent and very patient.', 40],
                    ['Sophie K.', 5, 'en', 'Smooth booking, fair prices and the WhatsApp support answered within minutes.', 58],
                    ['Marco P.', 3, 'it', 'Viaggio piacevole ma il pranzo a bordo era migliorabile.', 90, true],
                ],
            ],
            AccommodationSeeder::NILE_VIEW_PLACE_ID => [
                'summary' => ['rating' => 4.6, 'total' => 842, 'maps_url' => 'https://maps.google.com/?cid=1000000000000000002'],
                'reviews' => [
                    ['Elena R.', 5, 'it', 'Camera con vista sul Nilo spettacolare, colazione abbondante.', 9],
                    ['Tom H.', 4, 'en', 'Great location on the Corniche, rooftop pool is lovely. Rooms a bit dated.', 33],
                    ['Nadia F.', 5, 'en', 'Friendly staff and quiet rooms. Walking distance to Luxor temple.', 61],
                ],
            ],
        ];

        foreach ($places as $placeId => $place) {
            Setting::query()->updateOrCreate(['key' => "google_reviews.place.{$placeId}"], ['value' => [...$place['summary'], 'synced_at' => now()->toIso8601String()]]);

            foreach ($place['reviews'] as $index => $review) {
                GoogleReview::query()->create([
                    'place_id' => $placeId,
                    'google_review_id' => "places/{$placeId}/reviews/demo-{$index}",
                    'author_name' => $review[0],
                    'author_photo_url' => null,
                    'rating' => $review[1],
                    'language' => $review[2],
                    'text' => $review[3],
                    'published_at' => now()->subDays($review[4]),
                    'is_hidden' => $review[5] ?? false,
                    'synced_at' => now(),
                ]);
            }
        }
    }
}
