<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Destination;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

test('staff changes are recorded with who, what, the changes, IP and user agent', function () {
    $this->actingAs($this->admin)->withHeader('User-Agent', 'PestBrowser/1.0')
        ->post(route('admin.destinations.store'), ['name' => ['en' => 'Dahab'], 'is_active' => '1', 'sort' => 4])->assertRedirect();
    $destination = Destination::query()->where('slug', 'dahab')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.destinations.update', $destination), ['name' => ['en' => 'Dahab Blue Hole'], 'slug' => 'dahab', 'is_active' => '1'])->assertRedirect();
    $this->actingAs($this->admin)->delete(route('admin.destinations.destroy', $destination))->assertRedirect();

    $logs = ActivityLog::query()->where('subject_type', 'destination')->orderBy('id')->get();
    expect($logs->pluck('action')->map->value->all())->toBe(['created', 'updated', 'deleted'])
        ->and($logs[0]->causer_id)->toBe($this->admin->id)
        ->and($logs[0]->user_agent)->toBe('PestBrowser/1.0')
        ->and($logs[0]->ip_address)->toBe('127.0.0.1')
        ->and($logs[1]->description)->toBe('Destination “Dahab Blue Hole” updated')
        ->and($logs[1]->properties['changes']['new'])->toHaveKey('name');
});

test('booking status changes are logged as key actions; customer and system changes are not', function () {
    $booking = Booking::factory()->create();
    Addon::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed', 'note' => 'Paid at the office'])->assertRedirect();

    $log = ActivityLog::query()->where('action', 'status_changed')->sole();
    expect($log->subject_id)->toBe($booking->id)
        ->and($log->properties)->toEqual(['from' => 'awaiting_payment', 'to' => 'confirmed', 'note' => 'Paid at the office'])
        ->and(ActivityLog::query()->where('subject_type', 'addon')->count())->toBe(0);
});

test('super admins filter the log; other roles cannot open it', function () {
    $agent = staffUser(UserRole::Agent);
    $this->actingAs($agent)->post(route('admin.destinations.store'), ['name' => ['en' => 'Marsa Alam'], 'is_active' => '1'])->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.destinations.store'), ['name' => ['en' => 'Hurghada'], 'is_active' => '1'])->assertRedirect();

    $query = fn (array $filters) => $this->actingAs($this->admin)->getJson(route('admin.activity.data', ['draw' => 1, 'start' => 0, 'length' => 10, ...$filters]));

    expect($query([])->json('recordsTotal'))->toBe(2)
        ->and($query(['causer_id' => $agent->id])->json('recordsFiltered'))->toBe(1)
        ->and($query(['action' => 'created', 'subject_type' => 'destination'])->json('recordsFiltered'))->toBe(2)
        ->and($query(['search' => 'Hurghada'])->json('data.0.description'))->toContain('Hurghada')
        ->and($query(['date_from' => now()->addDay()->toDateString()])->json('recordsFiltered'))->toBe(0);

    $this->actingAs($this->admin)->get(route('admin.activity.index'))->assertOk();
    $this->actingAs($agent)->get(route('admin.activity.index'))->assertForbidden();
});
