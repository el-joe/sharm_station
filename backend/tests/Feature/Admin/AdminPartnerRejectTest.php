<?php

use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

test('a pending application is rejected with a reason shown on the partner page', function () {
    $partner = Partner::factory()->pending()->create(['company' => 'Shady Tours', 'user_id' => User::factory()->create()->id]);

    $this->actingAs($this->admin)->get(route('admin.partners.show', $partner))->assertOk()->assertSee('Reject the application');

    $this->actingAs($this->admin)->post(route('admin.partners.reject', $partner), ['rejection_reason' => ''])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($this->admin)->post(route('admin.partners.reject', $partner), ['rejection_reason' => 'We could not verify your licence.'])
        ->assertRedirect(route('admin.partners.show', $partner));

    $partner->refresh();
    expect($partner->status)->toBe(PartnerStatus::Rejected)
        ->and($partner->rejection_reason)->toBe('We could not verify your licence.')
        ->and($partner->rejected_at)->not->toBeNull()
        ->and($partner->user->role)->toBe(UserRole::Customer);

    $this->actingAs($this->admin)->get(route('admin.partners.show', $partner))->assertOk()->assertSee('Application rejected')->assertSee('We could not verify your licence.');
    $this->actingAs($this->admin)->getJson(route('admin.partners.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'rejected']))->assertJsonPath('recordsFiltered', 1);
    $this->assertDatabaseHas('activity_logs', ['action' => 'partner_rejected', 'subject_type' => 'partner', 'subject_id' => $partner->id]);
});

test('rejected applicants cannot use the partner panel', function () {
    $partner = Partner::factory()->create(['status' => PartnerStatus::Rejected, 'rejection_reason' => 'No licence']);
    Sanctum::actingAs($partner->user);

    $this->getJson('/api/v1/partner/dashboard')->assertForbidden()
        ->assertJsonPath('message', 'Your partner application was not approved, so the partner panel is not available.');
});

test('active partners are suspended, not rejected', function () {
    $partner = Partner::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.partners.reject', $partner), ['rejection_reason' => 'Changed our mind'])->assertSessionHasErrors('rejection_reason');
    expect($partner->fresh()->status)->toBe(PartnerStatus::Active);
});
