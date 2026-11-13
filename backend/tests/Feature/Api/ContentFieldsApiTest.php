<?php

use App\Models\Booking;
use App\Models\Destination;
use App\Models\GoogleReview;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripDay;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    Setting::factory()->create(['key' => 'google_reviews.place_id', 'value' => 'place-1']);
    Setting::factory()->create(['key' => 'google_reviews.place.place-1', 'value' => ['rating' => 4.8, 'total' => 1264]]);
});

test('trip detail has rating, unit label, practical info and transfer flags on the itinerary', function () {
    $trip = buggyTrip([
        'unit_label' => ['en' => 'buggy', 'it' => 'buggy'],
        'faq' => ['en' => [['question' => 'Is lunch included?', 'answer' => 'Yes.']], 'it' => [['question' => 'Il pranzo è incluso?', 'answer' => 'Sì.']]],
        'what_to_bring' => ['en' => ['Hat'], 'it' => ['Cappello']],
        'guide_languages' => ['en', 'it'],
        'cancellation_policy' => ['en' => 'Free cancellation up to 24 hours before.'],
    ]);
    $day = TripDay::factory()->create(['trip_id' => $trip->id, 'day_number' => 1]);
    $day->items()->create(['title' => ['en' => 'Hotel pick-up'], 'transfer_included' => true, 'sort' => 0]);

    $this->getJson("/api/v1/trips/{$trip->slug}?locale=it")
        ->assertOk()
        ->assertJsonPath('data.rating', 4.8)
        ->assertJsonPath('data.reviews_count', 1264)
        ->assertJsonPath('data.unit_label', 'buggy')
        ->assertJsonPath('data.faq.0.question', 'Il pranzo è incluso?')
        ->assertJsonPath('data.what_to_bring', ['Cappello'])
        ->assertJsonPath('data.guide_languages.1.label', 'Italiano')
        ->assertJsonPath('data.cancellation_policy', 'Free cancellation up to 24 hours before.')
        ->assertJsonPath('data.itinerary.0.items.0.transfer_included', true);
});

test('nile trips expose cabins without deck and localized facility and meal labels', function () {
    $nile = Trip::factory()->nile()->create([
        'vessel_facilities' => ['pool', 'sun_terrace'],
        'meal_type' => 'full_board',
        'cabins' => [['type' => 'standard', 'label' => ['en' => 'Standard cabin', 'it' => 'Cabina standard'], 'capacity' => 2]],
    ]);

    $response = $this->getJson("/api/v1/trips/{$nile->slug}?locale=it")
        ->assertOk()
        ->assertJsonPath('data.nile.facility_labels.1.label', 'Terrazza solarium')
        ->assertJsonPath('data.nile.meal_type_label', 'Pensione completa')
        ->assertJsonPath('data.nile.cabins.0.label', 'Cabina standard')
        ->assertJsonPath('data.nile.cabins.0.capacity', 2);

    expect($response->getContent())->not->toContain('deck');
});

test('trips can be sorted by rating and most booked', function () {
    $quiet = Trip::factory()->create(['featured' => true]);
    $popular = Trip::factory()->create(['featured' => false]);
    Booking::factory()->count(3)->create(['bookable_type' => 'trip', 'bookable_id' => $popular->id]);

    expect($this->getJson('/api/v1/trips?sort=most_booked')->assertOk()->json('data.0.id'))->toBe($popular->id);
    $this->getJson('/api/v1/trips?sort=rating')->assertOk()->assertJsonCount(2, 'data');
    expect($quiet->id)->not->toBe($popular->id);
});

test('meta has nested settings, whatsapp digits and destinations with image and trips count', function () {
    Setting::factory()->create(['key' => 'site.whatsapp', 'value' => '+20 100 000 0000']);
    Setting::factory()->create(['key' => 'site.name', 'value' => 'Sharm Station Viaggi']);
    $destination = Destination::factory()->create(['image' => '/images/placeholders/destination-1.jpg']);
    Trip::factory()->count(2)->create(['destination_id' => $destination->id]);

    $response = $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.site.name', 'Sharm Station Viaggi')
        ->assertJsonPath('data.whatsapp_digits', '201000000000')
        ->assertJsonPath('data.destinations.0.image', '/images/placeholders/destination-1.jpg')
        ->assertJsonPath('data.destinations.0.trips_count', 2);

    expect($response->json('data.settings')['site.name'])->toBe('Sharm Station Viaggi');
});

test('google reviews include the author country', function () {
    GoogleReview::factory()->create(['place_id' => 'place-1', 'author_country' => 'Italy']);

    $this->getJson('/api/v1/reviews/google')->assertOk()->assertJsonPath('data.reviews.0.author_country', 'Italy');
});
