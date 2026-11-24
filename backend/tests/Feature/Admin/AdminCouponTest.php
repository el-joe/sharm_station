<?php

use App\Enums\CouponDiscountType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Coupon;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

test('coupons are created in capitals with their rules, and the code is unique', function () {
    $this->actingAs($this->admin)->post(route('admin.coupons.store'), [
        'code' => ' winter-26 ', 'description' => 'Winter newsletter', 'discount_type' => 'fixed', 'value' => 25,
        'valid_from' => '2026-12-01', 'valid_to' => '2027-02-28', 'usage_limit' => 100, 'usage_limit_per_customer' => 1,
        'minimum_total' => 150, 'services' => ['trip', 'package'], 'partners_allowed' => '1', 'is_active' => '1',
    ])->assertRedirect(route('admin.coupons.index'));

    $coupon = Coupon::query()->firstOrFail();
    expect($coupon->code)->toBe('WINTER-26')
        ->and($coupon->discount_type)->toBe(CouponDiscountType::Fixed)
        ->and($coupon->services)->toBe(['trip', 'package'])
        ->and($coupon->partners_allowed)->toBeTrue()
        ->and($coupon->valid_to->toDateString())->toBe('2027-02-28');

    $this->actingAs($this->admin)->post(route('admin.coupons.store'), ['code' => 'WINTER-26', 'discount_type' => 'percent', 'value' => 10])
        ->assertSessionHasErrors(['code' => 'Another coupon already uses this code.']);
    $this->actingAs($this->admin)->post(route('admin.coupons.store'), ['code' => 'BIG', 'discount_type' => 'percent', 'value' => 120])
        ->assertSessionHasErrors('value');
    $this->actingAs($this->admin)->post(route('admin.coupons.store'), ['code' => 'BAD CODE!', 'discount_type' => 'percent', 'value' => 10])
        ->assertSessionHasErrors('code');
});

test('the list shows usage and the coupon can be edited and deleted', function () {
    $coupon = Coupon::factory()->create(['code' => 'SPRING', 'usage_limit' => 10]);
    $coupon->redemptions()->create(['booking_id' => Booking::factory()->create()->id, 'email' => 'a@example.com', 'discount_amount' => 12.5]);

    $row = $this->actingAs($this->admin)->getJson(route('admin.coupons.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->json('data.0');
    expect($row['redemptions_count'])->toBe('1 / 10')->and($row['redemptions_sum_discount_amount'])->toBe('€12.50');

    $this->actingAs($this->admin)->get(route('admin.coupons.edit', $coupon))->assertOk()->assertSee('Used 1 time of 10');
    $this->actingAs($this->admin)->put(route('admin.coupons.update', $coupon), ['code' => 'SPRING', 'discount_type' => 'percent', 'value' => 15, 'is_active' => '0'])
        ->assertRedirect(route('admin.coupons.index'));
    expect((float) $coupon->fresh()->value)->toBe(15.0)->and($coupon->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.coupons.destroy', $coupon))->assertRedirect();
    expect(Coupon::query()->count())->toBe(0);
});

test('editors cannot manage coupons', function () {
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.coupons.index'))->assertForbidden();
});
