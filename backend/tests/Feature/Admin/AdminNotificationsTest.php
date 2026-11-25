<?php

use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Enums\UserRole;
use App\Mail\NotificationMail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->admin = staffUser();
});

test('the notifications pages render for super admins only', function () {
    app(NotificationService::class)->seedDefaults();

    $this->actingAs($this->admin)->get(route('admin.notifications.index'))->assertOk()->assertSee('Balance reminder')->assertSee('Voucher PDF');
    $this->actingAs($this->admin)->get(route('admin.notifications.edit', ['deposit_confirmed', 'it']))->assertOk()->assertSee('confermata')->assertSee('{{reference}}', false);
    $this->actingAs($this->admin)->get(route('admin.notifications.log'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.notifications.render', ['review_invitation', 'en']))->assertOk()->assertSee('Write a review on Google');

    $this->actingAs(staffUser(UserRole::Agent))->get(route('admin.notifications.index'))->assertForbidden();
});

test('a template is saved, disabled and validated against the event placeholders', function () {
    $this->actingAs($this->admin)->put(route('admin.notifications.update', ['proof_rejected', 'en']), ['subject' => 'Proof {{reference}}', 'body' => 'Hi {{unknown_thing}}', 'is_enabled' => 1])
        ->assertSessionHasErrors('body');

    $this->actingAs($this->admin)->put(route('admin.notifications.update', ['proof_rejected', 'en']), ['subject' => 'Proof {{reference}}', 'body' => 'Hi {{customer_name}}: {{reason}}', 'is_enabled' => 0])
        ->assertRedirect(route('admin.notifications.edit', ['proof_rejected', 'en']));

    $template = NotificationTemplate::query()->sole();
    expect($template->event)->toBe(NotificationEvent::ProofRejected)->and($template->is_enabled)->toBeFalse();
});

test('the preview renders the unsaved form and the test email goes to the signed-in admin', function () {
    Mail::fake();

    $this->actingAs($this->admin)->postJson(route('admin.notifications.preview', ['booking_created', 'en']), ['subject' => 'Hello {{customer_name}}', 'body' => '**Ref** {{reference}}'])
        ->assertOk()
        ->assertJsonPath('subject', 'Hello Maria Rossi')
        ->assertJson(fn ($json) => $json->where('html', fn (string $html) => str_contains($html, '<strong>Ref</strong> BK-2041'))->etc());

    $this->actingAs($this->admin)->put(route('admin.notifications.test', ['booking_created', 'en']), ['subject' => 'Hello {{customer_name}}', 'body' => 'Body'])
        ->assertRedirect();

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->admin->email) && $mail->content->subject === '[TEST] Hello Maria Rossi');
});

test('the sending log lists, filters and resends emails', function () {
    $log = NotificationLog::query()->create(['event' => NotificationEvent::PaymentFailed, 'locale' => 'en', 'recipient' => 'maria@example.com', 'subject' => 'Payment failed',
        'status' => NotificationStatus::Failed, 'error' => 'Connection refused', 'context' => ['vars' => ['reference' => 'BK-1', 'customer_name' => 'Maria']]]);
    NotificationLog::query()->create(['event' => NotificationEvent::BookingCreated, 'locale' => 'en', 'recipient' => 'john@example.com', 'subject' => 'Booking', 'status' => NotificationStatus::Sent]);

    $this->actingAs($this->admin)->getJson(route('admin.notifications.log.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'failed']))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.status', fn (string $html) => str_contains($html, 'Connection refused'));

    $this->actingAs($this->admin)->post(route('admin.notifications.log.resend', $log))->assertRedirect();

    $copy = NotificationLog::query()->where('resent_from_id', $log->id)->sole();
    expect($copy->recipient)->toBe('maria@example.com')->and($copy->status)->toBe(NotificationStatus::Sent);
});
