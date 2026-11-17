<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ProofStatus;
use App\Enums\TransferStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingIncludedTransfer;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\TransferRequest;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    seedCurrencies();
    $this->admin = staffUser();
});

function pendingProof(Booking $booking, PaymentMethod $method): PaymentProof
{
    $path = UploadedFile::fake()->image('receipt.jpg')->store("payment-proofs/{$booking->id}", 'local');
    $booking->update(['status' => BookingStatus::AwaitingReceiptReview, 'payment_status' => PaymentStatus::AwaitingReceiptReview]);

    return PaymentProof::query()->create([
        'booking_id' => $booking->id, 'payment_method_id' => $method->id, 'purpose' => PaymentType::Deposit,
        'files' => [['path' => $path, 'name' => 'receipt.jpg', 'mime' => 'image/jpeg', 'size' => 1000]],
        'amount' => 30, 'status' => ProofStatus::Pending,
    ]);
}

test('transfer and payment screens render', function () {
    Storage::fake('local');
    $hub = TransferHub::factory()->create();
    $vehicle = VehicleType::factory()->create();
    $route = TransferFixedRoute::factory()->create(['transfer_hub_id' => $hub->id]);
    $booking = Booking::factory()->create();
    $method = PaymentMethod::factory()->create();
    $proof = pendingProof($booking, $method);
    $request = TransferRequest::query()->create([
        'booking_id' => $booking->id, 'transfer_hub_id' => $hub->id, 'vehicle_type_id' => $vehicle->id, 'destination_name' => 'Naama Bay',
        'destination_lat' => 27.91, 'destination_lng' => 34.33, 'pickup_at' => now()->addDays(3), 'passengers' => 2, 'distance_km' => 12,
        'duration_minutes' => 15, 'distance_provider' => 'haversine', 'needs_review' => true,
        'breakdown' => [['leg' => 'outbound', 'price' => 30, 'breakdown' => ['method' => 'distance', 'base_fare' => 10, 'distance_charge' => 14.4, 'waiting' => 0, 'surcharge' => 0, 'multiplier' => 1, 'minimum_fare' => 25, 'fees' => 5]]],
        'price' => 30,
    ]);

    $urls = [
        route('admin.transfers.requests.index'), route('admin.transfers.requests.show', $request), route('admin.transfers.hubs.index'),
        route('admin.transfers.hubs.create'), route('admin.transfers.hubs.edit', $hub), route('admin.transfers.vehicles.index'),
        route('admin.transfers.vehicles.create'), route('admin.transfers.vehicles.edit', $vehicle), route('admin.transfers.routes.index'),
        route('admin.transfers.routes.create'), route('admin.transfers.routes.edit', $route), route('admin.transfers.simulator'),
        route('admin.receipts.index'), route('admin.receipts.show', $proof), route('admin.payments.index'), route('admin.payment-methods.index'),
        route('admin.payment-methods.create'), route('admin.payment-methods.edit', $method), route('admin.bookings.show', $booking),
    ];

    foreach ($urls as $url) {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    foreach (['transfers.requests', 'transfers.hubs', 'transfers.vehicles', 'transfers.routes', 'receipts', 'payments', 'payment-methods'] as $table) {
        $this->actingAs($this->admin)->getJson(route("admin.{$table}.data", ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])->assertJsonCount($table === 'payments' ? 0 : 1, 'data');
    }
});

test('hubs, vehicles and fixed routes are managed from the admin', function () {
    $this->actingAs($this->admin)->post(route('admin.transfers.vehicles.store'), [
        'name' => ['en' => 'Van'], 'category' => 'van', 'capacity' => 12, 'luggage' => 12, 'multiplier' => 1.8, 'is_enabled' => '1',
    ])->assertRedirect(route('admin.transfers.vehicles.index'));
    $vehicle = VehicleType::query()->sole();

    $this->actingAs($this->admin)->post(route('admin.transfers.hubs.store'), [
        'name' => ['en' => 'Hurghada Airport'], 'type' => 'airport', 'lat' => 27.178, 'lng' => 33.799, 'radius_km' => 80,
        'base_fare' => 8, 'min_fare' => 20, 'fixed_fees' => 3,
        'distance_tiers' => [['up_to_km' => 30, 'rate' => 1.1], ['up_to_km' => '', 'rate' => 0.7]],
        'extras' => [['code' => 'child_seat', 'label' => 'Child seat', 'price' => 5]],
        'vehicle_type_ids' => [$vehicle->id], 'is_enabled' => '1',
    ])->assertSessionHasNoErrors();
    $hub = TransferHub::query()->sole();

    expect($hub->slug)->toBe('hurghada-airport')
        ->and($hub->distance_tiers[1]['up_to_km'])->toBeNull()
        ->and($hub->vehicle_type_ids)->toBe([$vehicle->id]);

    $this->actingAs($this->admin)->post(route('admin.transfers.routes.store'), [
        'transfer_hub_id' => $hub->id, 'destination_name' => ['en' => 'El Gouna'], 'lat' => 27.39, 'lng' => 33.68,
        'match_radius_km' => 2, 'price' => 30, 'is_enabled' => '1',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('admin.transfers.simulator', [
        'hub_id' => $hub->id, 'vehicle_type_id' => $vehicle->id, 'destination' => ['name' => 'El Gouna', 'lat' => 27.39, 'lng' => 33.68],
        'pickup_at' => now()->addDays(2)->format('Y-m-d').' 10:00', 'passengers' => 2,
    ]))->assertOk()->assertSee('Fixed route')->assertSee('€54.00');
});

test('transfer requests and included-transfer tasks are updated with driver details', function () {
    $booking = Booking::factory()->create();
    $task = BookingIncludedTransfer::query()->create(['booking_id' => $booking->id, 'from_place' => 'Airport', 'to_place' => 'Hotel', 'date' => now()->addDays(5)->toDateString(), 'status' => TransferStatus::Pending]);

    $this->actingAs($this->admin)->patch(route('admin.transfers.included.update', $task), ['status' => 'confirmed'])->assertSessionHasErrors('driver_name');
    $this->actingAs($this->admin)->patch(route('admin.transfers.included.update', $task), ['status' => 'confirmed', 'driver_name' => 'Ahmed', 'driver_phone' => '+20 100', 'car_plate' => 'ABC 123'])
        ->assertSessionHasNoErrors();

    expect($task->fresh()->status)->toBe(TransferStatus::Confirmed)->and($task->fresh()->driver_name)->toBe('Ahmed');
});

test('approving a proof records the deposit and confirms the booking; rejecting asks for a new one', function () {
    Storage::fake('local');
    $method = PaymentMethod::factory()->create();
    $booking = Booking::factory()->create(['total' => 100, 'deposit_amount' => 30]);
    $proof = pendingProof($booking, $method);

    $this->actingAs($this->admin)->get(route('admin.receipts.file', [$proof, 0]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($this->admin)->get(route('admin.receipts.file', [$proof, 5]))->assertNotFound();
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.receipts.file', [$proof, 0]))->assertForbidden();

    $this->actingAs($this->admin)->post(route('admin.receipts.approve', $proof))->assertRedirect(route('admin.receipts.index'));

    $booking->refresh();
    expect($proof->fresh()->status)->toBe(ProofStatus::Approved)
        ->and($proof->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($booking->payment_status)->toBe(PaymentStatus::DepositPaid)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payments()->sole()->type)->toBe(PaymentType::Deposit)
        ->and($booking->statusLogs()->first()->note)->toBe('Proof of payment approved');

    $this->actingAs($this->admin)->post(route('admin.receipts.approve', $proof))->assertSessionHasErrors('proof');

    $other = Booking::factory()->create();
    $second = pendingProof($other, $method);
    $this->actingAs($this->admin)->post(route('admin.receipts.reject', $second), [])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($this->admin)->post(route('admin.receipts.reject', $second), ['rejection_reason' => 'Amount does not match'])->assertRedirect();

    expect($second->fresh()->status)->toBe(ProofStatus::Rejected)
        ->and($other->fresh()->payment_status)->toBe(PaymentStatus::ProofRejected)
        ->and($other->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

test('recording the balance closes the booking as fully paid', function () {
    Storage::fake('local');
    $method = PaymentMethod::factory()->create();
    $booking = Booking::factory()->create(['total' => 100, 'deposit_amount' => 30]);
    $this->actingAs($this->admin)->post(route('admin.receipts.approve', pendingProof($booking, $method)));

    $this->actingAs($this->admin)->post(route('admin.bookings.payments.store', $booking), ['amount' => 80, 'paid_at' => now()->toDateString()])
        ->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post(route('admin.bookings.payments.store', $booking), ['amount' => 70, 'paid_at' => now()->toDateString(), 'type' => 'balance', 'note' => 'Cash on arrival'])
        ->assertRedirect(route('admin.bookings.show', $booking));

    $booking->refresh();
    expect($booking->payment_status)->toBe(PaymentStatus::FullyPaid)
        ->and($booking->status)->toBe(BookingStatus::FullyPaid)
        ->and($booking->dueAmount())->toBe(0.0);
});

test('payment methods are created with account details and currencies', function () {
    $this->actingAs($this->admin)->post(route('admin.payment-methods.store'), [
        'name' => ['en' => 'Vodafone Cash'], 'type' => 'manual', 'currencies' => ['EGP'], 'proof_required' => '1', 'is_enabled' => '1',
        'account_details' => [['label' => 'Number', 'value' => '010 0000 0000'], ['label' => '', 'value' => '']],
    ])->assertRedirect(route('admin.payment-methods.index'));

    $method = PaymentMethod::query()->sole();
    expect($method->code)->toBe('vodafone_cash')->and($method->account_details)->toHaveCount(1)->and($method->currencies)->toBe(['EGP']);

    $this->actingAs($this->admin)->post(route('admin.payment-methods.store'), ['name' => ['en' => 'Card'], 'type' => 'online'])->assertSessionHasErrors('driver');
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.receipts.index'))->assertForbidden();
});
