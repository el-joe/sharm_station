<?php

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

function customerTableQuery(array $filters = []): array
{
    return ['draw' => 1, 'start' => 0, 'length' => 10, ...$filters];
}

test('customers are listed with bookings count and total spent, without staff or partners', function () {
    $giulia = User::factory()->create(['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1111111', 'nationality_code' => 'IT']);
    Booking::factory()->create(['user_id' => $giulia->id, 'total' => 120]);
    Booking::factory()->create(['user_id' => $giulia->id, 'total' => 80]);
    Booking::factory()->create(['user_id' => $giulia->id, 'total' => 999, 'status' => BookingStatus::Cancelled]);
    User::factory()->create(['name' => 'Mark Taylor', 'nationality_code' => 'GB']);
    staffUser(UserRole::Agent);
    User::factory()->partner()->create();

    $all = $this->actingAs($this->admin)->getJson(route('admin.customers.data', customerTableQuery()))->assertOk();
    expect($all->json('recordsTotal'))->toBe(2);

    $row = $this->actingAs($this->admin)->getJson(route('admin.customers.data', customerTableQuery(['search' => '3331111111'])))
        ->assertJsonPath('recordsFiltered', 1)->json('data.0');
    expect($row['name'])->toContain('Giulia Rossi')->and($row['bookings_count'])->toBe(3)->and($row['total_spent'])->toBe('€200.00');

    $count = fn (array $filters) => $this->actingAs($this->admin)->getJson(route('admin.customers.data', customerTableQuery($filters)))->json('recordsFiltered');
    expect($count(['nationality_code' => 'GB']))->toBe(1)
        ->and($count(['with_bookings' => '0']))->toBe(1)
        ->and($count(['active' => '0']))->toBe(0);
});

test('the detail page shows the profile and bookings; notes and activation are saved', function () {
    $customer = User::factory()->create(['name' => 'Giulia Rossi']);
    $booking = Booking::factory()->create(['user_id' => $customer->id, 'reference' => 'BK-8001']);
    $customer->createToken('web');

    $this->actingAs($this->admin)->get(route('admin.customers.show', $customer))
        ->assertOk()->assertSee('Giulia Rossi')->assertSee('BK-8001')->assertSee(route('admin.bookings.show', $booking), false);

    $this->actingAs($this->admin)->put(route('admin.customers.notes', $customer), ['admin_notes' => 'Prefers WhatsApp'])->assertRedirect();
    expect($customer->fresh()->admin_notes)->toBe('Prefers WhatsApp');

    $this->actingAs($this->admin)->patch(route('admin.customers.toggle-active', $customer))->assertRedirect();
    expect($customer->fresh()->is_active)->toBeFalse()->and($customer->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email' => 'This account has been deactivated. Contact us for help.']);

    $this->actingAs($this->admin)->patch(route('admin.customers.toggle-active', $customer))->assertRedirect();
    expect($customer->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseHas('activity_logs', ['action' => 'deactivated', 'subject_type' => 'user', 'subject_id' => $customer->id, 'causer_id' => $this->admin->id]);
});

test('staff accounts are not customers and editors cannot open customers', function () {
    $this->actingAs($this->admin)->get(route('admin.customers.show', staffUser(UserRole::Agent)))->assertNotFound();
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.customers.index'))->assertForbidden();
});
