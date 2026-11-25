<?php

use App\Enums\NotificationEvent;
use App\Enums\UserRole;
use App\Mail\NotificationMail;
use App\Models\Building;
use App\Models\NotificationLog;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    Mail::fake();
    config(['app.frontend_url' => 'https://www.sharmstation.test']);
});

/**
 * Password link of the queued account email: [url, token].
 *
 * @return array{0: string, 1: string}
 */
function queuedPasswordLink(NotificationEvent $event, string $email): array
{
    $url = null;
    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($event, $email, &$url): bool {
        if ($mail->event !== $event || ! $mail->hasTo($email)) {
            return false;
        }

        $url = $mail->content->actionUrl;

        return true;
    });
    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

    return [(string) $url, (string) $query['token']];
}

function createAdminBookingForNewCustomer(string $email, string $locale = 'it'): void
{
    $room = Room::factory()->for(Building::factory())->create(['max_adults' => 2, 'max_total' => 2, 'base_price' => 80]);

    test()->actingAs(staffUser())->postJson(route('admin.bookings.store'), [
        'product_type' => 'room', 'booker' => 'new_customer', 'nationality_code' => 'IT', 'locale' => $locale, 'notify' => false,
        'check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'rooms' => [['room_id' => $room->id, 'adults' => 1]],
        'new_customer' => ['name' => 'Nuova Cliente', 'email' => $email, 'phone' => '+39 1'],
    ])->assertCreated();
}

test('a customer created with an admin booking gets a logged welcome email whose link sets the password', function () {
    createAdminBookingForNewCustomer('nuova@example.com');
    $customer = User::query()->where('email', 'nuova@example.com')->sole();

    [$url, $token] = queuedPasswordLink(NotificationEvent::CustomerWelcome, 'nuova@example.com');
    expect($url)->toStartWith('https://www.sharmstation.test/it/reset-password?token=')
        ->and($url)->toContain('email=nuova%40example.com');

    $log = NotificationLog::query()->where('event', NotificationEvent::CustomerWelcome)->sole();
    expect($log->recipient)->toBe('nuova@example.com')
        ->and($log->locale)->toBe('it')
        ->and($log->related_id)->toBe($customer->id)
        ->and($log->context['vars']['password_url'])->toBe('[redacted]')
        ->and(json_encode($log->context))->not->toContain($token);

    $this->travel(3)->days();
    auth()->forgetGuards();
    $this->postJson('/api/v1/auth/reset-password', ['email' => 'nuova@example.com', 'token' => $token, 'password' => 'new-secret-pass', 'password_confirmation' => 'new-secret-pass'])
        ->assertOk();
    $this->postJson('/api/v1/auth/login', ['email' => 'nuova@example.com', 'password' => 'new-secret-pass'])->assertOk();

    $this->postJson('/api/v1/auth/reset-password', ['email' => 'nuova@example.com', 'token' => $token, 'password' => 'other-secret-pass', 'password_confirmation' => 'other-secret-pass'])
        ->assertUnprocessable()->assertJsonValidationErrors(['token']);
});

test('the welcome link expires after seven days and a resend issues a new link', function () {
    createAdminBookingForNewCustomer('late@example.com', 'en');
    [, $token] = queuedPasswordLink(NotificationEvent::CustomerWelcome, 'late@example.com');

    $this->travel(8)->days();
    auth()->forgetGuards();
    $this->postJson('/api/v1/auth/reset-password', ['email' => 'late@example.com', 'token' => $token, 'password' => 'new-secret-pass', 'password_confirmation' => 'new-secret-pass'])
        ->assertUnprocessable()->assertJsonValidationErrors(['token']);

    $original = NotificationLog::query()->where('event', NotificationEvent::CustomerWelcome)->sole();
    $this->actingAs(staffUser())->post(route('admin.notifications.log.resend', $original))->assertRedirect();

    $copy = NotificationLog::query()->where('resent_from_id', $original->id)->sole();
    expect($copy->event)->toBe(NotificationEvent::CustomerWelcome)
        ->and($copy->context['vars']['password_url'])->toBe('[redacted]');
});

test('booking for an existing customer sends no welcome email', function () {
    User::factory()->create(['email' => 'known@example.com', 'role' => UserRole::Customer]);
    createAdminBookingForNewCustomer('known@example.com');

    Mail::assertNotQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::CustomerWelcome);
});

test('forgot password emails a reset link to website accounts only, with the same answer for unknown e-mails', function () {
    $customer = User::factory()->create(['email' => 'maria@example.com', 'role' => UserRole::Customer, 'locale' => 'en']);
    $staff = staffUser();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'Maria@Example.com'])->assertOk()->json('message');
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJsonPath('message', $known);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $staff->email])->assertOk();

    Mail::assertQueuedCount(1);
    [$url, $token] = queuedPasswordLink(NotificationEvent::PasswordReset, 'maria@example.com');
    expect($url)->toStartWith('https://www.sharmstation.test/en/reset-password?token=');
    $customer->createToken('web');

    $this->postJson('/api/v1/auth/reset-password', ['email' => 'maria@example.com', 'token' => $token, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
        ->assertOk();
    expect($customer->tokens()->count())->toBe(0);
});

test('a reset link expires after 60 minutes', function () {
    User::factory()->create(['email' => 'maria@example.com', 'role' => UserRole::Customer]);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'maria@example.com'])->assertOk();
    [, $token] = queuedPasswordLink(NotificationEvent::PasswordReset, 'maria@example.com');

    $this->travel(61)->minutes();
    $this->postJson('/api/v1/auth/reset-password', ['email' => 'maria@example.com', 'token' => $token, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
        ->assertUnprocessable()->assertJsonValidationErrors(['token']);
});
