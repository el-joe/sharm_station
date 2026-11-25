<?php

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;

return [
    /*
    | Online gateway drivers (SRS 10.2). A payment method of type "online" names one of these drivers and is only
    | offered to customers when its driver has its keys. No real keys ship with the project.
    | - fake: simulated hosted checkout and HMAC-signed webhooks for local use (off in production unless enabled).
    | - stripe: Stripe Checkout (official SDK); STRIPE_SECRET (sk_test_… = test mode) and STRIPE_WEBHOOK_SECRET.
    | - paypal: PayPal Orders v2 over REST; PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, PAYPAL_MODE, PAYPAL_WEBHOOK_ID.
    */
    'gateways' => [
        'fake' => [
            'class' => FakeGateway::class,
            'enabled' => (bool) env('PAYMENT_FAKE_ENABLED', env('APP_ENV', 'production') !== 'production'),
            'webhook_secret' => env('PAYMENT_FAKE_WEBHOOK_SECRET', 'fake-webhook-secret'),
        ],
        'stripe' => [
            'class' => StripeGateway::class,
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_base' => env('STRIPE_API_BASE'),
            'tolerance_seconds' => 300,
        ],
        'paypal' => [
            'class' => PayPalGateway::class,
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
    ],

    /* Where the customer returns after an online checkout (the Next.js booking page); ?token=…&payment=success|cancel is appended. */
    'return_url' => env('PAYMENT_RETURN_URL', rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/{locale}/booking/{reference}'),

    /* Proof of payment upload rules (SRS 10.3). */
    'proofs' => [
        'disk' => env('PAYMENT_PROOFS_DISK', 'local'),
        'max_files' => 3,
        'max_kilobytes' => 5120,
        'mimetypes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
];
