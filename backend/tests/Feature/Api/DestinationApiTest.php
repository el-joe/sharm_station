<?php

use App\Models\Building;
use App\Models\Destination;
use App\Models\Trip;

test('destinations are listed with their published trip count', function () {
    $luxor = Destination::factory()->create(['slug' => 'luxor', 'sort' => 1]);
    Destination::factory()->create(['slug' => 'cairo', 'sort' => 0]);
    Destination::factory()->create(['is_active' => false]);
    Trip::factory()->for($luxor)->create();
    Trip::factory()->for($luxor)->draft()->create();

    $this->getJson('/api/v1/destinations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'cairo')
        ->assertJsonPath('data.1.trips_count', 1);
});

test('a destination shows its trips and buildings in the requested locale', function () {
    $aswan = Destination::factory()->create(['slug' => 'aswan', 'name' => ['en' => 'Aswan', 'it' => 'Assuan']]);
    Trip::factory()->for($aswan)->create();
    Building::factory()->for($aswan)->create();

    $this->getJson('/api/v1/destinations/aswan?locale=it')
        ->assertOk()
        ->assertJsonPath('data.name', 'Assuan')
        ->assertJsonCount(1, 'data.trips')
        ->assertJsonCount(1, 'data.buildings');
});

test('an unknown destination returns 404', function () {
    $this->getJson('/api/v1/destinations/atlantis')->assertNotFound();
});
