<?php

use App\Models\AiSuggestion;
use App\Models\Building;
use App\Models\Destination;
use App\Models\Setting;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

/**
 * @return list<string>
 */
function locationPickerPages(): array
{
    return [
        route('admin.buildings.edit', Building::factory()->create(['lat' => 27.91, 'lng' => 34.33])),
        route('admin.transfers.hubs.edit', TransferHub::factory()->create()),
        route('admin.transfers.routes.edit', TransferFixedRoute::factory()->create()),
        route('admin.trips.edit', Trip::factory()->create(['lat' => 27.91, 'lng' => 34.33])),
        route('admin.settings.edit'),
        route('admin.ai-suggestions.show', AiSuggestion::factory()->create()->id),
    ];
}

test('without a browser key the forms show plain coordinates and a Google Maps preview link', function () {
    config(['services.google.maps_browser_key' => null]);

    foreach (locationPickerPages() as $url) {
        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('data-location-picker', false)
            ->assertSee('Preview on Google Maps')
            ->assertSee('Add GOOGLE_MAPS_BROWSER_KEY to enable the map picker.')
            ->assertDontSee('data-maps-key', false)
            ->assertDontSee('data-location-map', false);
    }
});

test('with a browser key the forms render the map picker', function () {
    config(['services.google.maps_browser_key' => 'test-browser-key']);

    foreach (locationPickerPages() as $url) {
        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('data-maps-key="test-browser-key"', false)
            ->assertSee('data-location-map', false)
            ->assertDontSee('Add GOOGLE_MAPS_BROWSER_KEY');
    }
});

test('the building picker is bound to the address and coordinate inputs and links the saved point', function () {
    $building = Building::factory()->create(['address' => 'Naama Bay', 'lat' => 27.9158, 'lng' => 34.33]);

    $this->actingAs($this->admin)->get(route('admin.buildings.edit', $building))
        ->assertOk()
        ->assertSee('data-lat-input="#f-lat" data-lng-input="#f-lng"', false)
        ->assertSee('data-address-input="#f-address"', false)
        ->assertSee('https://www.google.com/maps?q=27.9158,34.33', false);
});

test('the server key is never rendered into admin pages', function () {
    config(['services.google.maps_browser_key' => 'test-browser-key', 'services.google.maps_server_key' => 'secret-server-key']);

    $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk()->assertDontSee('secret-server-key');
});

test('the office location is saved from Settings and feeds the meta contact', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'site_name' => 'Sharm Station Viaggi', 'default_deposit_percent' => 30, 'hold_minutes' => 30, 'preview_commission' => 15,
        'site_address' => 'Naama Bay, Sharm El Sheikh', 'site_lat' => '27.9150123', 'site_lng' => '34.3291234',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.edit'));

    expect(Setting::query()->where('key', 'site.lat')->value('value'))->toBe(27.9150123);

    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.contact.address', 'Naama Bay, Sharm El Sheikh')
        ->assertJsonPath('data.contact.lat', 27.9150123)
        ->assertJsonPath('data.contact.lng', 34.3291234);
});

test('office coordinates must come in pairs and within range', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'site_name' => 'Sharm Station Viaggi', 'default_deposit_percent' => 30, 'hold_minutes' => 30, 'preview_commission' => 15,
        'site_lat' => '95',
    ])->assertSessionHasErrors(['site_lat', 'site_lng']);
});

test('the trip meeting point and its coordinates are saved from the editor', function () {
    $trip = Trip::factory()->create(['destination_id' => Destination::factory()->create()->id]);

    $this->actingAs($this->admin)->put(route('admin.trips.update', $trip), [
        'destination_id' => $trip->destination_id,
        'title' => ['en' => 'Ras Mohammed snorkelling'],
        'status' => 'published',
        'pricing_mode' => 'per_person',
        'min_persons' => 1,
        'deposit_percent' => 30,
        'private_enabled' => '0',
        'group_enabled' => '1',
        'base_price' => 25,
        'meeting_point' => ['en' => 'Naama Bay marina', 'it' => 'Marina di Naama Bay'],
        'lat' => '27.9123456',
        'lng' => '34.3254321',
    ])->assertSessionHasNoErrors();

    $trip->refresh();
    expect($trip->getTranslations('meeting_point'))->toBe(['en' => 'Naama Bay marina', 'it' => 'Marina di Naama Bay'])
        ->and((float) $trip->lat)->toBe(27.9123456)
        ->and((float) $trip->lng)->toBe(34.3254321);
});
