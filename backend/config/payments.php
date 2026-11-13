<?php

use App\Services\Payments\FakeGateway;
use App\Services\Payments\StripeGateway;

return [
    /*
    | Online gateway drivers (SRS 10.2). A payment method of type "online" names one of these drivers.
    | No real keys ship with the project: the "fake" driver simulates a hosted checkout and signed webhooks,
    | and "stripe" only talks to Stripe when STRIPE_SECRET is set.
    */
    'gateways' => [
        'fake' => [
            'class' => FakeGateway::class,
            'webhook_secret' => env('PAYMENT_FAKE_WEBHOOK_SECRET', 'fake-webhook-secret'),
        ],
        'stripe' => [
            'class' => StripeGateway::class,
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1'),
            'tolerance_seconds' => 300,
        ],
    ],

    /* Where the customer returns after an online checkout (the Next.js booking page). */
    'return_url' => env('PAYMENT_RETURN_URL', 'http://localhost:3000/en/booking/{reference}'),

    /* Proof of payment upload rules (SRS 10.3). */
    'proofs' => [
        'disk' => env('PAYMENT_PROOFS_DISK', 'local'),
        'max_files' => 3,
        'max_kilobytes' => 5120,
        'mimetypes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
];
