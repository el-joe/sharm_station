<?php

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Enums\UserRole;
use App\Mcp\Servers\SharmStationServer;
use App\Mcp\Tools\CreateAccommodationSuggestionTool;
use App\Mcp\Tools\GetAccommodationTool;
use App\Mcp\Tools\GetPackageTool;
use App\Mcp\Tools\GetTripTool;
use App\Mcp\Tools\ListAccommodationsTool;
use App\Mcp\Tools\ListPackagesTool;
use App\Mcp\Tools\ListTransfersTool;
use App\Mcp\Tools\ListTripsTool;
use App\Mcp\Tools\SuggestSeoTool;
use App\Models\AiSuggestion;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Destination;
use App\Models\Package;
use App\Models\Partner;
use App\Models\ProductPrice;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * JSON-RPC call to the MCP endpoint (legacy initialize style, as used by curl and older clients).
 *
 * @param  array<string, mixed>  $params
 */
function mcpCall(string $method, array $params = [], ?string $token = null): TestResponse
{
    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($token !== null) {
        $headers['Authorization'] = 'Bearer '.$token;
    }

    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
}

function mcpToken(UserRole $role = UserRole::Editor, array $abilities = ['mcp']): string
{
    return User::factory()->role($role)->create()->createToken('Claude test', $abilities)->plainTextToken;
}

/**
 * Staff user acting through an MCP token, for the Server::tool() helpers.
 */
function mcpStaff(): User
{
    $user = staffUser(UserRole::Agent);
    $token = $user->createToken('Claude desktop', ['mcp']);

    return $user->withAccessToken($token->accessToken);
}

describe('authentication', function () {
    test('requests without a token are rejected', function () {
        mcpCall('initialize', ['protocolVersion' => '2025-06-18'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate')
            ->assertJsonPath('error.code', -32600);
    });

    test('tokens without the explicit mcp ability are rejected, including wildcard tokens', function (array $abilities) {
        mcpCall('tools/list', [], mcpToken(UserRole::SuperAdmin, $abilities))->assertUnauthorized();
    })->with([
        'wildcard login token' => [['*']],
        'other ability' => [['partner']],
    ]);

    test('an mcp token owned by a customer or partner is rejected', function (UserRole $role) {
        mcpCall('tools/list', [], mcpToken($role))->assertUnauthorized();
    })->with([UserRole::Customer, UserRole::Partner]);

    test('a revoked token is rejected', function () {
        $user = staffUser();
        $token = $user->createToken('old', ['mcp']);
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

        mcpCall('tools/list', [], $token->plainTextToken)->assertUnauthorized();
    });

    test('a staff mcp token initializes the server and lists the nine tools', function () {
        $token = mcpToken();

        mcpCall('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']], $token)
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'sharm-station');

        $tools = mcpCall('tools/list', [], $token)->assertOk()->json('result.tools');

        expect(collect($tools)->pluck('name')->sort()->values()->all())->toBe([
            'create_accommodation_suggestion', 'get_accommodation', 'get_package', 'get_trip', 'list_accommodations',
            'list_packages', 'list_transfers', 'list_trips', 'suggest_seo',
        ]);
    });

    test('an mcp token cannot be used on the website API', function () {
        $this->getJson('/api/v1/me/bookings', ['Authorization' => 'Bearer '.mcpToken()])->assertUnauthorized();

        $customerToken = User::factory()->role(UserRole::Customer)->create()->createToken('web')->plainTextToken;
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/me/bookings', ['Authorization' => 'Bearer '.$customerToken])->assertOk();
    });

    test('tool calls over HTTP return structured data and update last_used_at', function () {
        Trip::factory()->create(['title' => ['en' => 'Giftun Island snorkelling'], 'slug' => 'giftun-island']);
        $user = staffUser();
        $token = $user->createToken('Claude', ['mcp']);

        mcpCall('tools/call', ['name' => 'list_trips', 'arguments' => ['q' => 'giftun']], $token->plainTextToken)
            ->assertOk()
            ->assertJsonPath('result.structuredContent.items.0.slug', 'giftun-island');

        expect($token->accessToken->fresh()->last_used_at)->not->toBeNull();
    });
});

describe('read tools', function () {
    test('list_trips filters by type, destination and text, and get_trip returns content, prices per group and SEO', function () {
        $hurghada = Destination::factory()->create(['slug' => 'hurghada']);
        $trip = snorkellingTrip(['destination_id' => $hurghada->id, 'seo' => ['title' => 'Snorkel trip', 'description' => 'Old description']]);
        Trip::factory()->nile()->create();
        ProductPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $trip->id, 'nationality_group_id' => nationalityGroup('egyptian')->id, 'adult_price' => 12, 'child_price' => 6]);
        SeasonPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $trip->id, 'nationality_group_id' => null, 'adult_price' => 30]);

        SharmStationServer::actingAs(mcpStaff())->tool(ListTripsTool::class, ['type' => 'single_day', 'destination' => 'hurghada', 'q' => 'ras'])
            ->assertOk()
            ->assertStructuredContentPath('total', 1)
            ->assertStructuredContentPath('items.0.id', $trip->id);

        SharmStationServer::actingAs(mcpStaff())->tool(GetTripTool::class, ['id' => $trip->id, 'locale' => 'it'])
            ->assertOk()
            ->assertStructuredContentPath('title', 'Snorkeling a Ras Mohammed')
            ->assertStructuredContentPath('seo.locales.en.meta_title', 'Snorkel trip')
            ->assertStructuredContentPath('price_matrix.product_base.Adult', 25)
            ->assertStructuredContentPath('price_matrix.base_by_group.0.group', 'egyptian')
            ->assertStructuredContentPath('price_matrix.base_by_group.0.prices.Adult', 12)
            ->assertStructuredContentPath('price_matrix.seasons.0.group', 'all')
            ->assertStructuredContentPath('private_tiers.0.price_per_person_eur', 70);
    });

    test('get_trip reports unknown ids as a tool error', function () {
        SharmStationServer::actingAs(mcpStaff())->tool(GetTripTool::class, ['id' => 999])
            ->assertHasErrors(['Trip 999 not found']);
    });

    test('invalid arguments are reported as validation errors', function () {
        SharmStationServer::actingAs(mcpStaff())->tool(ListTripsTool::class, ['type' => 'cruise', 'locale' => 'de'])
            ->assertHasErrors();
    });

    test('packages, transfers and accommodations are readable', function () {
        $package = Package::factory()->create(['title' => ['en' => 'Cairo & Luxor 7 days']]);
        $hub = TransferHub::factory()->create();
        $route = TransferFixedRoute::factory()->for($hub, 'hub')->create(['price' => 40]);
        $building = Building::factory()->create(['name' => ['en' => 'Aldau Beach']]);
        Room::factory()->for($building)->create(['base_price' => 140]);

        SharmStationServer::actingAs(mcpStaff())->tool(ListPackagesTool::class)->assertOk()->assertStructuredContentPath('items.0.id', $package->id);
        SharmStationServer::actingAs(mcpStaff())->tool(GetPackageTool::class, ['id' => $package->id])->assertOk()->assertSee('Cairo & Luxor 7 days');
        SharmStationServer::actingAs(mcpStaff())->tool(ListTransfersTool::class)->assertOk()
            ->assertStructuredContentPath('hubs.0.fixed_routes.0.id', $route->id)
            ->assertStructuredContentPath('hubs.0.fixed_routes.0.price_matrix.product_base.Price per vehicle', 40);
        SharmStationServer::actingAs(mcpStaff())->tool(ListAccommodationsTool::class, ['q' => 'aldau'])->assertOk()
            ->assertStructuredContentPath('items.0.rooms.0.base_price_per_night_eur', 140);
        SharmStationServer::actingAs(mcpStaff())->tool(GetAccommodationTool::class, ['id' => $building->id])->assertOk()
            ->assertStructuredContentPath('rooms.0.price_matrix.product_base.Per night', 140);
    });

    test('read tools never expose customer, booking, payment or partner data', function () {
        $partner = Partner::factory()->create(['company' => 'Secret Partner Srl']);
        $trip = buggyTrip();
        Booking::factory()->create(['lead_name' => 'Mario Customer', 'lead_email' => 'mario@example.test', 'partner_id' => $partner->id]);
        $building = Building::factory()->create();
        Room::factory()->for($building)->create();

        $responses = [
            SharmStationServer::actingAs(mcpStaff())->tool(ListTripsTool::class),
            SharmStationServer::actingAs(mcpStaff())->tool(GetTripTool::class, ['id' => $trip->id]),
            SharmStationServer::actingAs(mcpStaff())->tool(ListTransfersTool::class),
            SharmStationServer::actingAs(mcpStaff())->tool(GetAccommodationTool::class, ['id' => $building->id]),
        ];

        foreach ($responses as $response) {
            $response->assertOk()->assertDontSee(['Secret Partner', 'Mario Customer', 'mario@example.test', 'partner_price', 'commission', 'lead_']);
        }
    });
});

describe('suggestion tools', function () {
    test('create_accommodation_suggestion validates like the admin forms', function () {
        Destination::factory()->create(['slug' => 'hurghada']);
        Building::factory()->create(['slug' => 'aldau-beach']);

        SharmStationServer::actingAs(mcpStaff())->tool(CreateAccommodationSuggestionTool::class, [
            'name_en' => 'Aldau Beach',
            'type' => 'castle',
            'destination' => 'atlantis',
            'stars' => 7,
            'taxes_included' => false,
            'rooms' => [['name_en' => 'Double', 'room_type' => 'double', 'max_adults' => 3, 'max_total' => 2, 'base_price_per_night' => -5]],
        ])->assertHasErrors(['already exists', 'Unknown destination', 'tax per night', 'at least the maximum adults']);

        expect(AiSuggestion::query()->count())->toBe(0);
    });

    test('create_accommodation_suggestion stores a pending suggestion and tells Claude an admin must approve it', function () {
        Destination::factory()->create(['slug' => 'hurghada']);
        $staff = mcpStaff();

        SharmStationServer::actingAs($staff)->tool(CreateAccommodationSuggestionTool::class, [
            'name_en' => 'Steigenberger Aldau Beach',
            'name_it' => 'Steigenberger Aldau Beach',
            'type' => 'hotel',
            'destination' => 'hurghada',
            'stars' => 5,
            'check_in_from' => '15:00',
            'check_out_until' => '12:00',
            'amenities' => ['wifi', 'beach'],
            'description_en' => 'Adults-friendly resort.',
            'notes' => 'From the hotel website.',
            'rooms' => [
                ['name_en' => 'Deluxe sea view', 'room_type' => 'double', 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3, 'base_price_per_night' => 140, 'beds_en' => '1 king'],
                ['name_en' => 'Family suite', 'room_type' => 'family', 'max_adults' => 4, 'max_children' => 2, 'max_total' => 6, 'base_price_per_night' => 210],
            ],
        ])->assertOk()
            ->assertStructuredContentPath('status', 'pending')
            ->assertSee('an admin must review');

        $suggestion = AiSuggestion::query()->sole();
        expect($suggestion->type)->toBe(SuggestionType::AccommodationCreate)
            ->and($suggestion->status)->toBe(SuggestionStatus::Pending)
            ->and($suggestion->created_by_token_id)->toBe($staff->currentAccessToken()->id)
            ->and($suggestion->payload['building']['slug'])->toBe('steigenberger-aldau-beach')
            ->and($suggestion->payload['rooms'])->toHaveCount(2)
            ->and($suggestion->payload['rooms'][0]['base_price'])->toBe(140)
            ->and(Building::query()->count())->toBe(0);
    });

    test('suggest_seo stores the proposal with the current values as original and changes nothing', function () {
        $trip = Trip::factory()->create(['slug' => 'giftun-island-trip', 'seo' => ['title' => 'Giftun Island Trip', 'description' => 'Visit Giftun.']]);

        SharmStationServer::actingAs(mcpStaff())->tool(SuggestSeoTool::class, [
            'subject_type' => 'trip',
            'subject_id' => $trip->id,
            'slug' => 'giftun-island-snorkelling-hurghada',
            'en' => ['meta_title' => 'Giftun Island Snorkelling from Hurghada', 'keywords' => ['giftun island', 'snorkelling hurghada']],
            'it' => ['meta_title' => 'Snorkeling all\'isola di Giftun', 'og_description' => 'Acqua cristallina.'],
        ])->assertOk()->assertStructuredContentPath('status', 'pending');

        $suggestion = AiSuggestion::query()->sole();
        expect($suggestion->subject->is($trip))->toBeTrue()
            ->and($suggestion->original['slug'])->toBe('giftun-island-trip')
            ->and($suggestion->original['locales']['en']['meta_title'])->toBe('Giftun Island Trip')
            ->and($suggestion->payload['locales']['en']['keywords'])->toBe(['giftun island', 'snorkelling hurghada'])
            ->and($trip->fresh()->slug)->toBe('giftun-island-trip')
            ->and($trip->fresh()->seo['title'])->toBe('Giftun Island Trip');
    });

    test('suggest_seo rejects too long values, unknown products and a slug for rooms', function () {
        $room = Room::factory()->create();

        SharmStationServer::actingAs(mcpStaff())->tool(SuggestSeoTool::class, ['subject_type' => 'trip', 'subject_id' => 1, 'en' => ['meta_title' => str_repeat('x', 71)]])
            ->assertHasErrors(['70']);
        SharmStationServer::actingAs(mcpStaff())->tool(SuggestSeoTool::class, ['subject_type' => 'package', 'subject_id' => 999, 'en' => ['meta_title' => 'Ok']])
            ->assertHasErrors(['Package 999 not found']);
        SharmStationServer::actingAs(mcpStaff())->tool(SuggestSeoTool::class, ['subject_type' => 'room', 'subject_id' => $room->id, 'slug' => 'new-room'])
            ->assertHasErrors(['no URL slug']);

        expect(AiSuggestion::query()->count())->toBe(0);
    });
});
