<?php

use App\Enums\ActivityAction;
use App\Enums\AddonPricingBasis;
use App\Enums\BookingStatus;
use App\Enums\CommissionCategory;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    Mail::fake();
    $this->admin = staffUser();
    $this->building = Building::factory()->create();
    $this->double = Room::factory()->for($this->building)->create(['name' => ['en' => 'Double'], 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3, 'base_price' => 80]);
    $this->triple = Room::factory()->for($this->building)->create(['name' => ['en' => 'Triple'], 'max_adults' => 3, 'max_children' => 2, 'max_total' => 3, 'base_price' => 100]);
});

function adminStayPayload(array $rooms, array $overrides = []): array
{
    return [
        'product_type' => 'room',
        'booker' => 'customer',
        'nationality_code' => 'IT',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'rooms' => $rooms,
        'lead' => ['name' => 'Walk In', 'email' => 'walkin@example.com'],
        'notify' => false,
        ...$overrides,
    ];
}

/**
 * Accommodation booking made through the admin for later edits.
 */
function adminStayBooking(array $rooms): Booking
{
    test()->actingAs(test()->admin)->postJson(route('admin.bookings.store'), adminStayPayload($rooms))->assertCreated();

    return Booking::query()->latest('id')->firstOrFail();
}

test('the new booking page and the change page render', function () {
    $this->actingAs($this->admin)->get(route('admin.bookings.create'))->assertOk()->assertSee('data-booking-editor', false)->assertSee('On behalf of a partner');

    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);
    $this->get(route('admin.bookings.edit', $booking))->assertOk()->assertSee('Preview changes');
    $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Change dates or rooms')->assertSee('Release hold');
});

test('staff quote and create a multi-room booking for a walk-in with placeholder travellers', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.bookings.quote'), adminStayPayload([['room_id' => $this->double->id, 'adults' => 2], ['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [6]]]))
        ->assertOk()
        ->assertJsonPath('data.total', 360);

    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2], ['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [6]]]);

    expect($booking->rooms()->count())->toBe(2)
        ->and($booking->travelers()->pluck('first_name')->all())->toBe(['Walk', 'Adult 2', 'Adult 3', 'Child 1'])
        ->and($booking->user_id)->toBeNull()
        ->and(ActivityLog::query()->where('action', ActivityAction::BookingCreated)->where('subject_id', $booking->id)->exists())->toBeTrue();
    Mail::assertNothingQueued();
});

test('an invalid admin quote answers 422 with the allocation error', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.bookings.quote'), adminStayPayload([['room_id' => $this->double->id, 'adults' => 0, 'children_ages' => [6]]]))
        ->assertUnprocessable()
        ->assertJsonPath('data.valid', false);

    $this->postJson(route('admin.bookings.store'), adminStayPayload([['room_id' => $this->double->id, 'adults' => 2]], ['lead' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lead.name', 'lead.email']);
});

test('a new customer account is created, or an existing one reused, for the booking', function () {
    $this->actingAs($this->admin)->postJson(route('admin.bookings.store'), adminStayPayload([['room_id' => $this->double->id, 'adults' => 1]], [
        'booker' => 'new_customer', 'lead' => [], 'new_customer' => ['name' => 'Nuova Cliente', 'email' => 'Nuova@Example.com', 'phone' => '+39 1'],
    ]))->assertCreated();

    $customer = User::query()->where('email', 'nuova@example.com')->sole();
    expect($customer->role)->toBe(UserRole::Customer)
        ->and(Booking::query()->latest('id')->first()->user_id)->toBe($customer->id)
        ->and(Booking::query()->latest('id')->first()->lead_name)->toBe('Nuova Cliente');

    $existing = User::factory()->create(['role' => UserRole::Customer, 'name' => 'Existing Client']);
    $this->getJson(route('admin.bookings.customers', ['q' => 'Existing']))->assertOk()->assertJsonPath('results.0.id', $existing->id);
    $this->postJson(route('admin.bookings.store'), adminStayPayload([['room_id' => $this->triple->id, 'adults' => 1]], ['customer_id' => $existing->id, 'lead' => []]))->assertCreated();
    expect(Booking::query()->latest('id')->first()->lead_email)->toBe($existing->email);
});

test('a booking on behalf of a partner uses the partner prices and commission (same engine as the partner panel)', function () {
    $trip = snorkellingTrip();
    $partner = Partner::factory()->create(['company' => 'Nile Tours Srl']);
    partnerOverride($partner, CommissionCategory::SingleDayTrips, 20);

    $this->actingAs($this->admin)->getJson(route('admin.bookings.product-options', ['type' => 'trip', 'key' => $trip->slug]))
        ->assertOk()->assertJsonPath('data.per_unit', false)->assertJsonPath('data.booking_types.group', 'Group');

    $this->postJson(route('admin.bookings.store'), [
        'product_type' => 'trip', 'booker' => 'partner', 'partner_id' => $partner->id, 'nationality_code' => 'IT',
        'slug' => $trip->slug, 'booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2,
        'lead' => ['name' => 'End Client', 'email' => 'client@example.com'], 'notify' => false,
    ])->assertCreated();

    $booking = Booking::query()->latest('id')->firstOrFail();
    expect($booking->partner_id)->toBe($partner->id)
        ->and($booking->user_id)->toBe($partner->user_id)
        ->and((float) $booking->retail_total)->toBe(50.0)
        ->and((float) $booking->partner_total)->toBe(40.0)
        ->and((float) $booking->commission_amount)->toBe(10.0);

    $pending = Partner::factory()->pending()->create();
    $this->postJson(route('admin.bookings.quote'), [
        'product_type' => 'trip', 'booker' => 'partner', 'partner_id' => $pending->id, 'nationality_code' => 'IT',
        'slug' => $trip->slug, 'booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2,
    ])->assertUnprocessable()->assertJsonValidationErrors(['partner_id']);
});

test('changing dates and rooms shows a diff, recalculates the price, moves the holds and is logged', function () {
    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);
    $change = ['check_in' => '2026-12-05', 'check_out' => '2026-12-08', 'rooms' => [['room_id' => $this->triple->id, 'adults' => 2]], 'note' => 'Guest asked'];

    $preview = $this->postJson(route('admin.bookings.preview', $booking), $change)->assertOk()->assertJsonPath('data.valid', true)->json('data.diff');
    $rows = collect($preview)->keyBy('field');
    expect($rows['check_in'])->toMatchArray(['before' => '2026-12-01', 'after' => '2026-12-05', 'changed' => true])
        ->and($rows['total'])->toMatchArray(['before' => '€160.00', 'after' => '€300.00']);
    expect($booking->fresh()->total)->toEqual(160);

    $this->putJson(route('admin.bookings.update', $booking), $change)->assertOk();

    $booking->refresh();
    expect((float) $booking->total)->toBe(300.0)
        ->and($booking->check_in->toDateString())->toBe('2026-12-05')
        ->and($booking->rooms()->sole()->room_id)->toBe($this->triple->id)
        ->and(RoomAvailability::query()->where('booking_id', $booking->id)->pluck('room_id')->unique()->all())->toBe([$this->triple->id])
        ->and(RoomAvailability::query()->where('booking_id', $booking->id)->count())->toBe(3)
        ->and(ActivityLog::query()->where('action', ActivityAction::BookingModified)->where('subject_id', $booking->id)->exists())->toBeTrue();
});

test('a change onto taken nights or breaking the allocation is refused', function () {
    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);
    RoomAvailability::factory()->create(['room_id' => $this->triple->id, 'date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Booked]);

    $this->postJson(route('admin.bookings.preview', $booking), ['rooms' => [['room_id' => $this->triple->id, 'adults' => 2]]])
        ->assertUnprocessable()->assertJsonPath('data.errors.check_in.0', 'Room 1 is not available on 2 Dec 2026.');
    $this->putJson(route('admin.bookings.update', $booking), ['rooms' => [['room_id' => $this->double->id, 'adults' => 1]]])
        ->assertUnprocessable()->assertJsonPath('errors.rooms.0', 'Every guest must be in a room.');

    $booking->update(['status' => BookingStatus::Cancelled]);
    $this->postJson(route('admin.bookings.preview', $booking), ['check_in' => '2026-12-10', 'check_out' => '2026-12-12'])->assertUnprocessable()->assertJsonValidationErrors(['booking']);
});

test('the calendar moves a booking by drag and drop after a dry run, and refuses taken nights', function () {
    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);
    $move = ['booking_id' => $booking->id, 'from_room_id' => $this->double->id, 'to_room_id' => $this->triple->id, 'check_in' => '2026-12-02'];

    $this->actingAs($this->admin)->postJson(route('admin.calendar.move'), [...$move, 'dry_run' => true])
        ->assertOk()->assertJsonPath('moved', false);
    expect($booking->fresh()->check_in->toDateString())->toBe('2026-12-01');

    $this->postJson(route('admin.calendar.move'), $move)->assertOk()->assertJsonPath('moved', true);
    $booking->refresh();
    expect($booking->check_in->toDateString())->toBe('2026-12-02')
        ->and($booking->check_out->toDateString())->toBe('2026-12-04')
        ->and($booking->rooms()->sole()->room_id)->toBe($this->triple->id);

    RoomAvailability::factory()->create(['room_id' => $this->double->id, 'date' => '2026-12-03', 'status' => RoomAvailabilityStatus::Closed]);
    $this->postJson(route('admin.calendar.move'), [...$move, 'from_room_id' => $this->triple->id, 'to_room_id' => $this->double->id])
        ->assertUnprocessable()->assertJsonPath('errors.check_in.0', 'Room 1 is not available on 3 Dec 2026.');

    $this->postJson(route('admin.calendar.move'), [...$move, 'from_room_id' => $this->double->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['from_room_id']);

    $tooSmall = Room::factory()->for($this->building)->create(['max_adults' => 1, 'max_total' => 1]);
    $this->postJson(route('admin.calendar.move'), [...$move, 'from_room_id' => $this->triple->id, 'to_room_id' => $tooSmall->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['rooms.0.adults']);
});

test('the calendar lists held bookings and staff release a hold', function () {
    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);

    $this->get(route('admin.calendar.index', ['month' => '2026-12']))->assertOk()->assertSee('Rooms on hold')->assertSee('data-occupancy-move', false);
    $this->post(route('admin.bookings.release-hold', $booking))->assertRedirect(route('admin.bookings.show', $booking));

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(RoomAvailability::query()->where('booking_id', $booking->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('action', ActivityAction::HoldReleased)->exists())->toBeTrue();

    $this->post(route('admin.bookings.release-hold', $booking))->assertSessionHasErrors('booking');
});

test('editors cannot create, change or move bookings', function () {
    $booking = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);
    $editor = staffUser(UserRole::Editor);

    $this->actingAs($editor)->get(route('admin.bookings.create'))->assertForbidden();
    $this->actingAs($editor)->postJson(route('admin.calendar.move'), ['booking_id' => $booking->id, 'from_room_id' => $this->double->id, 'to_room_id' => $this->triple->id, 'check_in' => '2026-12-02'])->assertForbidden();
});

/**
 * Trip booking made through the admin for later edits.
 *
 * @param  array<string, mixed>  $overrides
 */
function adminTripBooking(Trip $trip, array $overrides = []): Booking
{
    test()->actingAs(test()->admin)->postJson(route('admin.bookings.store'), [
        'product_type' => 'trip', 'booker' => 'customer', 'nationality_code' => 'IT', 'slug' => $trip->slug,
        'booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2,
        'lead' => ['name' => 'Anna Rossi', 'email' => 'anna@example.com'], 'notify' => false, ...$overrides,
    ])->assertCreated();

    return Booking::query()->latest('id')->firstOrFail();
}

test('changing the party of a trip previews the diff, reprices it and syncs the travellers', function () {
    $trip = snorkellingTrip();
    $booking = adminTripBooking($trip);
    $booking->travelers()->where('first_name', 'Adult 2')->update(['first_name' => 'Marco']);
    $change = ['adults' => 3, 'child_ages' => [6], 'note' => 'Friend joins'];

    $rows = collect($this->postJson(route('admin.bookings.preview', $booking), $change)->assertOk()->assertJsonPath('data.valid', true)->json('data.diff'))->keyBy('field');
    expect($rows['party'])->toMatchArray(['before' => '2 adults', 'after' => '3 adults, 1 child (age 6)', 'changed' => true])
        ->and($rows['total'])->toMatchArray(['before' => '€50.00', 'after' => '€87.50', 'changed' => true]);

    $this->putJson(route('admin.bookings.update', $booking), $change)->assertOk();

    $booking->refresh();
    expect($booking->adults)->toBe(3)
        ->and($booking->children)->toBe(1)
        ->and((float) $booking->total)->toBe(87.5)
        ->and($booking->travelers()->orderBy('sort')->pluck('first_name')->all())->toBe(['Anna', 'Marco', 'Adult 3', 'Child 1'])
        ->and($booking->travelers()->where('first_name', 'Child 1')->value('age'))->toBe(6)
        ->and(ActivityLog::query()->where('action', ActivityAction::BookingModified)->where('subject_id', $booking->id)->value('description'))->toContain('Travellers: 2 adults → 3 adults, 1 child (age 6)');

    $this->putJson(route('admin.bookings.update', $booking), ['adults' => 1, 'child_ages' => []])->assertOk();
    expect($booking->travelers()->pluck('first_name')->all())->toBe(['Anna'])
        ->and((float) $booking->fresh()->total)->toBe(25.0);
});

test('a party change outside the product limits or for a stay is refused', function () {
    $booking = adminTripBooking(snorkellingTrip());

    $this->postJson(route('admin.bookings.preview', $booking), ['adults' => 13])
        ->assertUnprocessable()->assertJsonPath('data.errors.adults.0', 'This trip allows at most 12 persons per booking.');
    $this->putJson(route('admin.bookings.update', $booking), ['adults' => 13])->assertUnprocessable()->assertJsonValidationErrors(['adults']);
    $this->postJson(route('admin.bookings.preview', $booking), ['adults' => 0])->assertUnprocessable()->assertJsonValidationErrors(['adults']);
    expect($booking->fresh()->adults)->toBe(2);

    $stay = adminStayBooking([['room_id' => $this->double->id, 'adults' => 2]]);
    $this->postJson(route('admin.bookings.preview', $stay), ['adults' => 3])->assertUnprocessable()->assertJsonValidationErrors(['adults']);
});

test('a partner booking keeps the partner commission when the party changes', function () {
    $partner = Partner::factory()->create();
    partnerOverride($partner, CommissionCategory::SingleDayTrips, 20);
    $booking = adminTripBooking(snorkellingTrip(), ['booker' => 'partner', 'partner_id' => $partner->id, 'lead' => ['name' => 'End Client', 'email' => 'client@example.com']]);

    $rows = collect($this->postJson(route('admin.bookings.preview', $booking), ['adults' => 3])->assertOk()->json('data.diff'))->keyBy('field');
    expect($rows['commission_amount'])->toMatchArray(['before' => '€10.00', 'after' => '€15.00']);

    $this->putJson(route('admin.bookings.update', $booking), ['adults' => 3])->assertOk();
    $booking->refresh();
    expect((float) $booking->retail_total)->toBe(75.0)
        ->and((float) $booking->partner_total)->toBe(60.0)
        ->and((float) $booking->commission_amount)->toBe(15.0);
});

test('units and add-ons of a per-unit trip are changed with the seat check', function () {
    $trip = buggyTrip();
    $double = $trip->unitOptions->firstWhere('seats', 2);
    $family = $trip->unitOptions->firstWhere('seats', 4);
    $photos = Addon::factory()->basis(AddonPricingBasis::PerBooking, 30)->create(['name' => ['en' => 'Photo pack']]);
    $trip->addons()->attach($photos->id, ['sort' => 0]);
    $booking = adminTripBooking($trip, ['units' => [['unit_option_id' => $double->id, 'quantity' => 1]]]);
    expect((float) $booking->total)->toBe(50.0);

    $this->get(route('admin.bookings.edit', $booking))->assertOk()->assertSee('data-unit="'.$double->id.'"', false)->assertSee('Photo pack');

    $this->postJson(route('admin.bookings.preview', $booking), ['adults' => 3, 'units' => [['unit_option_id' => $double->id, 'quantity' => 1]]])
        ->assertUnprocessable()->assertJsonPath('data.errors.units.0', 'The selected units have 2 seats for 3 travellers. Add another unit.');

    $change = ['adults' => 3, 'units' => [['unit_option_id' => $family->id, 'quantity' => 1], ['unit_option_id' => $double->id, 'quantity' => 0]], 'addons' => [['addon_id' => $photos->id]]];
    $rows = collect($this->postJson(route('admin.bookings.preview', $booking), $change)->assertOk()->json('data.diff'))->keyBy('field');
    expect($rows['units']['after'])->toBe('1 × Family buggy')
        ->and($rows['addons'])->toMatchArray(['before' => 'None', 'after' => '1 × Photo pack']);

    $this->putJson(route('admin.bookings.update', $booking), $change)->assertOk();
    $booking->refresh();
    expect((float) $booking->total)->toBe(120.0)
        ->and($booking->units()->sole()->trip_unit_option_id)->toBe($family->id)
        ->and($booking->addons()->sole()->addon_id)->toBe($photos->id);
});
