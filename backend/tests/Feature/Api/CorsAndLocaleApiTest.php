<?php

use App\Models\Trip;

test('the Next.js origin is allowed by CORS', function () {
    $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/trips')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
});

test('other origins are not allowed by CORS', function () {
    $response = $this->withHeaders([
        'Origin' => 'https://evil.example',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/trips');

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example');
});

test('translations fall back to English when the Italian text is missing', function () {
    Trip::factory()->create(['title' => ['en' => 'Only English'], 'summary' => ['en' => 'Summary', 'it' => 'Sommario']]);

    $this->getJson('/api/v1/trips?locale=it')
        ->assertJsonPath('data.0.title', 'Only English')
        ->assertJsonPath('data.0.summary', 'Sommario');

    $this->withHeaders(['Accept-Language' => 'it-IT,it;q=0.9'])->getJson('/api/v1/trips')
        ->assertJsonPath('data.0.summary', 'Sommario');
});
