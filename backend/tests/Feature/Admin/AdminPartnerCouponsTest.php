<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Partner;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
    $this->partner = Partner::factory()->create(['coupons_allowed' => false]);
});

test('the partner page shows the Coupons allowed toggle', function () {
    $this->actingAs($this->admin)->get(route('admin.partners.show', $this->partner))
        ->assertOk()
        ->assertSee('Coupons allowed')
        ->assertSee('data-ajax-toggle="'.route('admin.partners.coupons', $this->partner).'"', false);
});

test('the toggle saves coupons_allowed and writes the activity log', function () {
    $this->actingAs($this->admin)->patchJson(route('admin.partners.coupons', $this->partner), ['coupons_allowed' => true])
        ->assertOk()
        ->assertJsonPath('coupons_allowed', true);

    expect($this->partner->fresh()->coupons_allowed)->toBeTrue();

    $log = ActivityLog::query()->where('subject_type', 'partner')->sole();
    expect($log->causer_id)->toBe($this->admin->id)
        ->and($log->properties)->toBe(['coupons_allowed' => true]);

    $this->actingAs($this->admin)->patchJson(route('admin.partners.coupons', $this->partner), ['coupons_allowed' => false])->assertOk();
    expect($this->partner->fresh()->coupons_allowed)->toBeFalse()
        ->and(ActivityLog::query()->where('subject_type', 'partner')->count())->toBe(2);
});

test('editors cannot change the partner coupons setting', function () {
    $this->actingAs(staffUser(UserRole::Editor))->patchJson(route('admin.partners.coupons', $this->partner), ['coupons_allowed' => true])->assertForbidden();

    expect($this->partner->fresh()->coupons_allowed)->toBeFalse();
});
