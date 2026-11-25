<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\RefundService;
use Illuminate\Support\Facades\Http;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;

/**
 * Stripe SDK transport double: records requests and answers with canned JSON per path.
 */
class FakeStripeClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @param array<string, array<string, mixed>> $responses */
    public function __construct(private array $responses) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];
        $path = parse_url($absUrl, PHP_URL_PATH);

        return [json_encode($this->responses[$path] ?? ['error' => ['message' => 'not found']]), isset($this->responses[$path]) ? 200 : 404, new CaseInsensitiveArray([])];
    }
}

beforeEach(function () {
    seedCurrencies();
    $this->booking = Booking::factory()->create(['reference' => 'BK-8001', 'access_token' => 'gw-token', 'total' => 100, 'deposit_amount' => 30, 'currency' => 'USD', 'exchange_rate' => 1.1, 'locale' => 'it']);
});

afterEach(fn () => ApiRequestor::setHttpClient(null));

test('gateways without keys are hidden from the payment methods', function () {
    PaymentMethod::factory()->online('stripe')->create(['code' => 'stripe_card', 'currencies' => null]);
    PaymentMethod::factory()->online('paypal')->create(['code' => 'paypal', 'currencies' => null]);
    PaymentMethod::factory()->create(['code' => 'bank_transfer']);

    $this->getJson('/api/v1/bookings/BK-8001/payment-methods?token=gw-token')->assertOk()->assertJsonPath('data.*.code', ['bank_transfer']);

    config(['payments.gateways.stripe.secret' => 'sk_test_1', 'payments.gateways.paypal.client_id' => 'id', 'payments.gateways.paypal.client_secret' => 'secret']);
    $this->getJson('/api/v1/bookings/BK-8001/payment-methods?token=gw-token')->assertOk()
        ->assertJsonCount(3, 'data');
});

test('stripe checkout charges the deposit in the booking currency and returns to the booking page', function () {
    config(['payments.gateways.stripe.secret' => 'sk_test_1', 'payments.gateways.stripe.webhook_secret' => 'whsec_1', 'app.frontend_url' => 'https://site.test', 'payments.return_url' => 'https://site.test/{locale}/booking/{reference}']);
    $client = new FakeStripeClient(['/v1/checkout/sessions' => ['id' => 'cs_test_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/cs_test_1', 'expires_at' => now()->addHour()->getTimestamp()]]);
    ApiRequestor::setHttpClient($client);
    $card = PaymentMethod::factory()->online('stripe')->create(['code' => 'stripe_card']);

    $this->postJson('/api/v1/bookings/BK-8001/checkout', ['token' => 'gw-token', 'payment_method_id' => $card->id])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/cs_test_1')
        ->assertJsonPath('data.session_reference', 'cs_test_1');

    $params = $client->requests[0]['params'];
    expect($params['line_items'][0]['price_data']['currency'])->toBe('usd')
        ->and($params['line_items'][0]['price_data']['unit_amount'])->toBe(3300)
        ->and($params['success_url'])->toBe('https://site.test/it/booking/BK-8001?token=gw-token&payment=success')
        ->and($params['cancel_url'])->toBe('https://site.test/it/booking/BK-8001?token=gw-token&payment=cancel');

    $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1']]]);
    $timestamp = now()->getTimestamp();
    $this->call('POST', '/api/v1/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_1')], $payload)
        ->assertOk();

    expect(Payment::query()->sole()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('stripe refunds call the refunds API with the payment intent', function () {
    config(['payments.gateways.stripe.secret' => 'sk_test_1']);
    $client = new FakeStripeClient(['/v1/refunds' => ['id' => 're_1', 'object' => 'refund', 'status' => 'succeeded']]);
    ApiRequestor::setHttpClient($client);
    $card = PaymentMethod::factory()->online('stripe')->create(['code' => 'stripe_card']);
    $this->booking->update(['status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::DepositPaid]);
    Payment::query()->create(['booking_id' => $this->booking->id, 'payment_method_id' => $card->id, 'type' => PaymentType::Deposit, 'status' => PaymentRecordStatus::Succeeded,
        'amount' => 30, 'currency' => 'USD', 'exchange_rate' => 1.1, 'amount_in_currency' => 33, 'transaction_id' => 'pi_1', 'paid_at' => now()]);

    $refunds = app(RefundService::class)->issueForCancellation($this->booking, 10, staffUser());

    expect($refunds[0]->transaction_id)->toBe('re_1')
        ->and($client->requests[0]['params'])->toMatchArray(['payment_intent' => 'pi_1', 'amount' => 1100]);
});

test('paypal creates an order, captures it on return and verifies webhooks with paypal', function () {
    config(['payments.gateways.paypal' => [...config('payments.gateways.paypal'), 'client_id' => 'cid', 'client_secret' => 'csecret', 'mode' => 'sandbox', 'webhook_id' => 'WH-1'],
        'payments.return_url' => 'https://site.test/{locale}/booking/{reference}']);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21', 'expires_in' => 32400]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER-1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1']]], 201),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response(['id' => 'ORDER-1', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED']]]]]], 201),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::sequence()->push(['verification_status' => 'FAILURE'])->push(['verification_status' => 'SUCCESS']),
    ]);
    $paypal = PaymentMethod::factory()->online('paypal')->create(['code' => 'paypal']);

    $this->postJson('/api/v1/bookings/BK-8001/checkout', ['token' => 'gw-token', 'payment_method_id' => $paypal->id])
        ->assertCreated()->assertJsonPath('data.checkout_url', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && $request['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '33.00']
        && str_contains($request['payment_source']['paypal']['experience_context']['return_url'], '/api/v1/payments/paypal/return?payment='));

    $payment = Payment::query()->sole();
    $this->get('/api/v1/payments/paypal/return?payment='.$payment->id.'&token=WRONG')->assertNotFound();
    $this->get('/api/v1/payments/paypal/return?payment='.$payment->id.'&token=ORDER-1&PayerID=X')
        ->assertRedirect('https://site.test/it/booking/BK-8001?token=gw-token&payment=success');

    expect($payment->fresh()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($payment->fresh()->transaction_id)->toBe('CAP-1')
        ->and($this->booking->fresh()->payment_status)->toBe(PaymentStatus::DepositPaid);

    $headers = ['HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA', 'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert', 'HTTP_PAYPAL_TRANSMISSION_ID' => 't1',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig', 'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(), 'CONTENT_TYPE' => 'application/json'];
    $payload = json_encode(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAP-1', 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']]]]);
    $this->call('POST', '/api/v1/payments/webhooks/paypal', [], [], [], $headers, $payload)->assertStatus(400);
    $this->call('POST', '/api/v1/payments/webhooks/paypal', [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', '/api/v1/payments/webhooks/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'verify-webhook-signature') && $request['webhook_id'] === 'WH-1' && $request['transmission_id'] === 't1');
    expect(Payment::query()->count())->toBe(1);
});

test('paypal refunds the capture', function () {
    config(['payments.gateways.paypal.client_id' => 'cid', 'payments.gateways.paypal.client_secret' => 'csecret']);
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'A21']),
        '*/v2/payments/captures/CAP-9/refund' => Http::response(['id' => 'REF-1', 'status' => 'COMPLETED'], 201),
    ]);
    $paypal = PaymentMethod::factory()->online('paypal')->create(['code' => 'paypal']);
    $payment = Payment::query()->create(['booking_id' => $this->booking->id, 'payment_method_id' => $paypal->id, 'type' => PaymentType::Deposit, 'status' => PaymentRecordStatus::Succeeded,
        'amount' => 30, 'currency' => 'USD', 'exchange_rate' => 1.1, 'amount_in_currency' => 33, 'transaction_id' => 'CAP-9', 'paid_at' => now()]);

    $result = app(PaymentGatewayManager::class)->driver('paypal')->refund($payment, 11);

    expect($result->reference)->toBe('REF-1')->and($result->pending)->toBeFalse();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refund') && $request['amount'] === ['currency_code' => 'USD', 'value' => '11.00']);
});
