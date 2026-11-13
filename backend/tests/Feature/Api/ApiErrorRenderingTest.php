<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->get('api/v1/_test/boom', fn () => throw new RuntimeException('Secret failure in /var/www/app.php'));
});

test('api errors never expose trace or file when debug is off', function () {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/v1/_test/boom')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);

    expect($response->getContent())->not->toContain('trace')->not->toContain('/var/www')->not->toContain('RuntimeException');

    $this->getJson('/api/v1/trips/does-not-exist')->assertNotFound()->assertExactJson(['message' => 'Trip not found.']);
    $this->getJson('/api/v1/nothing-here')->assertNotFound()->assertJsonMissingPath('trace');
    $this->postJson('/api/v1/newsletter', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors' => ['email']]);
});

test('debug mode keeps the detailed error for local development', function () {
    config(['app.debug' => true]);

    $this->getJson('/api/v1/_test/boom')->assertStatus(500)->assertJsonStructure(['message', 'exception', 'trace']);
});

test('cors origins come from the comma separated FRONTEND_URLS list', function () {
    expect(config('cors.allowed_origins'))->toContain('http://localhost:3000');

    $this->options('/api/v1/meta', [], ['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'GET'])
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
});
