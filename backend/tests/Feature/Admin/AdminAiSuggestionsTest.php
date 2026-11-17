<?php

use App\Enums\PublishStatus;
use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Enums\UserRole;
use App\Models\AiSuggestion;
use App\Models\Building;
use App\Models\Room;
use App\Models\Trip;
use App\Services\McpTokenService;
use App\Services\SeoService;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

function seoSuggestion(Trip $trip, array $payload = []): AiSuggestion
{
    return AiSuggestion::factory()->create([
        'type' => SuggestionType::SeoUpdate,
        'subject_type' => 'trip',
        'subject_id' => $trip->id,
        'payload' => [
            'subject_type' => 'trip',
            'subject_type_label' => 'Trip',
            'subject_label' => 'Giftun Island snorkelling',
            'slug' => 'giftun-island-snorkelling',
            'locales' => ['en' => ['meta_title' => 'Giftun Island Snorkelling'], 'it' => ['meta_title' => 'Snorkeling a Giftun']],
            'notes' => 'Keyword research',
            ...$payload,
        ],
        'original' => ['slug' => $trip->slug, 'locales' => ['en' => ['meta_title' => 'Old title']]],
    ]);
}

describe('inbox', function () {
    test('the inbox, its data endpoint and both review pages render', function () {
        $trip = Trip::factory()->create(['seo' => ['title' => 'Old title', 'description' => 'Old description']]);
        $seo = seoSuggestion($trip);
        $accommodation = AiSuggestion::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.ai-suggestions.index'))->assertOk()->assertSee('AI suggestions');
        $this->actingAs($this->admin)->getJson(route('admin.ai-suggestions.data', ['draw' => 1, 'status' => 'pending']))
            ->assertOk()->assertJsonPath('recordsTotal', 2)->assertSee('Giftun Island snorkelling')->assertSee('Coral Bay Resort');
        $this->actingAs($this->admin)->getJson(route('admin.ai-suggestions.data', ['draw' => 1, 'type' => 'seo_update']))
            ->assertOk()->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->admin)->get(route('admin.ai-suggestions.show', $seo->id))
            ->assertOk()->assertSee('Old title')->assertSee('Giftun Island Snorkelling')->assertSee('Approve and apply');
        $this->actingAs($this->admin)->get(route('admin.ai-suggestions.show', $accommodation->id))
            ->assertOk()->assertSee('Coral Bay Resort')->assertSee('Double sea view')->assertSee('Approve (creates building)');
    });

    test('editors can review but not approve an accommodation suggestion', function () {
        $editor = staffUser(UserRole::Editor);
        $suggestion = AiSuggestion::factory()->create();

        $this->actingAs($editor)->get(route('admin.ai-suggestions.show', $suggestion->id))->assertOk()->assertDontSee('Approve (creates building)');
        $this->actingAs($editor)->post(route('admin.ai-suggestions.approve-accommodation', $suggestion->id), $suggestion->payload)->assertForbidden();
        expect(Building::query()->count())->toBe(0);
    });
});

describe('SEO approval', function () {
    test('approving applies the edited values and the slug to the subject in both locales', function () {
        $trip = Trip::factory()->create(['slug' => 'giftun-trip', 'seo' => ['title' => 'Old title', 'description' => 'Old description']]);
        $suggestion = seoSuggestion($trip);

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.approve-seo', $suggestion->id), [
            'slug' => 'giftun-island-snorkelling',
            'locales' => [
                'en' => ['meta_title' => 'Giftun Island Snorkelling – edited', 'keywords' => 'giftun, snorkelling , giftun'],
                'it' => ['meta_title' => 'Snorkeling a Giftun', 'og_description' => 'Acqua cristallina.'],
            ],
        ])->assertRedirect(route('admin.ai-suggestions.show', $suggestion->id))->assertSessionHasNoErrors();

        $trip->refresh();
        expect($trip->slug)->toBe('giftun-island-snorkelling')
            ->and($trip->seo['title'])->toBe('Giftun Island Snorkelling – edited')
            ->and($trip->seo['description'])->toBe('Old description')
            ->and($trip->seo['locales']['en']['keywords'])->toBe(['giftun', 'snorkelling'])
            ->and($trip->seo['locales']['it']['og_description'])->toBe('Acqua cristallina.')
            ->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Approved)
            ->and($suggestion->fresh()->reviewed_by)->toBe($this->admin->id);
    });

    test('saving the trip form afterwards keeps the Italian SEO', function () {
        $trip = Trip::factory()->create(['seo' => ['title' => 'Old', 'locales' => ['it' => ['meta_title' => 'Titolo IT']]]]);
        $merged = SeoService::mergeFormSeo($trip->seo, ['title' => 'New EN', 'description' => 'Desc']);

        expect($merged['locales']['it']['meta_title'])->toBe('Titolo IT')
            ->and($merged['locales']['en']['meta_title'])->toBe('New EN');
    });

    test('a slug already used by another trip is refused and nothing changes', function () {
        Trip::factory()->create(['slug' => 'taken-slug']);
        $trip = Trip::factory()->create(['slug' => 'giftun-trip']);
        $suggestion = seoSuggestion($trip);

        $this->actingAs($this->admin)->from(route('admin.ai-suggestions.show', $suggestion->id))
            ->post(route('admin.ai-suggestions.approve-seo', $suggestion->id), ['slug' => 'taken-slug', 'locales' => ['en' => ['meta_title' => 'New']]])
            ->assertSessionHasErrors('slug');

        expect($trip->fresh()->slug)->toBe('giftun-trip')
            ->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Pending);
    });

    test('rejecting needs a reason and does not change the subject; decided suggestions are read-only', function () {
        $trip = Trip::factory()->create(['slug' => 'giftun-trip', 'seo' => ['title' => 'Old title']]);
        $suggestion = seoSuggestion($trip);

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.reject', $suggestion->id), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.reject', $suggestion->id), ['review_note' => 'Keywords too generic.'])
            ->assertRedirect(route('admin.ai-suggestions.index'));

        expect($suggestion->fresh()->status)->toBe(SuggestionStatus::Rejected)
            ->and($suggestion->fresh()->review_note)->toBe('Keywords too generic.')
            ->and($trip->fresh()->slug)->toBe('giftun-trip')
            ->and($trip->fresh()->seo['title'])->toBe('Old title');

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.approve-seo', $suggestion->id), ['locales' => ['en' => ['meta_title' => 'Late']]])
            ->assertSessionHasErrors('suggestion');
        expect($trip->fresh()->seo['title'])->toBe('Old title');

        $this->actingAs($this->admin)->get(route('admin.ai-suggestions.show', $suggestion->id))
            ->assertOk()->assertSee('Decided suggestions are read-only')->assertDontSee('Approve and apply');
    });
});

describe('accommodation approval', function () {
    test('approving creates the draft building and its rooms in one go', function () {
        $suggestion = AiSuggestion::factory()->create();
        $payload = $suggestion->payload;
        $payload['building']['name']['en'] = 'Coral Bay Resort & Spa';
        $payload['building']['slug'] = '';
        $payload['rooms'][] = ['name' => ['en' => 'Family suite'], 'room_type' => 'family', 'max_adults' => 4, 'max_children' => 2, 'max_total' => 6, 'base_price' => 210, 'min_nights' => 2];

        $response = $this->actingAs($this->admin)->post(route('admin.ai-suggestions.approve-accommodation', $suggestion->id), $payload);

        $building = Building::query()->sole();
        $response->assertRedirect(route('admin.buildings.edit', $building))->assertSessionHasNoErrors();
        expect($building->slug)->toBe('coral-bay-resort-spa')
            ->and($building->status)->toBe(PublishStatus::Draft)
            ->and($building->stars)->toBe(4)
            ->and($building->rooms()->count())->toBe(2)
            ->and((float) Room::query()->where('room_type', 'family')->value('base_price'))->toBe(210.0)
            ->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Approved)
            ->and($suggestion->fresh()->subject_id)->toBe($building->id);
    });

    test('the admin can publish on approval', function () {
        $suggestion = AiSuggestion::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.approve-accommodation', $suggestion->id), [...$suggestion->payload, 'publish' => '1'])
            ->assertSessionHasNoErrors();

        expect(Building::query()->sole()->status)->toBe(PublishStatus::Published);
    });

    test('invalid edits are refused and nothing is created', function () {
        $suggestion = AiSuggestion::factory()->create();
        $payload = $suggestion->payload;
        $payload['rooms'][0]['max_total'] = 1;
        $payload['building']['taxes_included'] = '0';

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.approve-accommodation', $suggestion->id), $payload)
            ->assertSessionHasErrors(['rooms.0.max_total', 'building.tax_per_night']);

        expect(Building::query()->count())->toBe(0)->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Pending);
    });

    test('rejecting an accommodation suggestion creates nothing', function () {
        $suggestion = AiSuggestion::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.ai-suggestions.reject', $suggestion->id), ['review_note' => 'Hotel already exists.'])->assertRedirect();

        expect(Building::query()->count())->toBe(0)->and($suggestion->fresh()->status)->toBe(SuggestionStatus::Rejected);
    });
});

describe('MCP access', function () {
    test('super admins see the page with the connection instructions', function () {
        config(['app.url' => 'https://sharmstationviaggi.test']);

        $this->actingAs($this->admin)->get(route('admin.mcp.index'))->assertOk()
            ->assertSee('claude mcp add --transport http sharm-station https://sharmstationviaggi.test/mcp --header &quot;Authorization: Bearer &lt;your-token&gt;&quot;', false)
            ->assertSee('mcpServers')->assertSee('https://sharmstationviaggi.test/mcp')
            // Same settings tabs as Settings → Commissions.
            ->assertSee('<a href="'.route('admin.nationality-groups.index').'" class="tab-link">Nationality groups</a>', false);
    });

    test('other staff roles cannot manage MCP tokens', function () {
        $this->actingAs(staffUser(UserRole::Agent))->get(route('admin.mcp.index'))->assertForbidden();
        $this->actingAs(staffUser(UserRole::Editor))->post(route('admin.mcp.store'), ['name' => 'Mine'])->assertForbidden();
    });

    test('a token is created with only the mcp ability and shown once, then revoked', function () {
        $this->actingAs($this->admin)->post(route('admin.mcp.store'), ['name' => 'Claude desktop – Marco'])
            ->assertRedirect(route('admin.mcp.index'))->assertSessionHas('mcp_token');

        $token = PersonalAccessToken::query()->sole();
        expect($token->abilities)->toBe([McpTokenService::ABILITY])->and($token->tokenable_id)->toBe($this->admin->id);

        $this->actingAs($this->admin)->get(route('admin.mcp.index'))->assertSee('Claude desktop – Marco')->assertSee('Active');

        $this->actingAs($this->admin)->delete(route('admin.mcp.destroy', $token->id))->assertRedirect(route('admin.mcp.index'));
        expect(McpTokenService::isRevoked($token->fresh()))->toBeTrue();
        $this->actingAs($this->admin)->get(route('admin.mcp.index'))->assertSee('Revoked');
    });

    test('login tokens are not listed or revocable as MCP tokens', function () {
        $loginToken = $this->admin->createToken('web')->accessToken;

        $this->actingAs($this->admin)->delete(route('admin.mcp.destroy', $loginToken->id))->assertNotFound();
        expect($loginToken->fresh()->expires_at)->toBeNull();
    });
});
