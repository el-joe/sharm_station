<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'places_key' => env('GOOGLE_PLACES_API_KEY'),
        'place_id' => env('GOOGLE_PLACE_ID'),
        'places_endpoint' => env('GOOGLE_PLACES_ENDPOINT', 'https://places.googleapis.com/v1/places'),

        /*
        | Browser key: Maps JavaScript API + Places API (New) + Maps Embed API, HTTP-referrer restricted.
        | Rendered into admin pages only (location picker). Empty = plain lat/lng inputs.
        */
        'maps_browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),

        /*
        | Server key: Routes API only, IP restricted. Never sent to a browser. Empty = Haversine estimate.
        */
        'maps_server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'routes_endpoint' => env('GOOGLE_ROUTES_ENDPOINT', 'https://routes.googleapis.com/directions/v2:computeRoutes'),
        'routes_timeout' => (int) env('GOOGLE_ROUTES_TIMEOUT', 5),
    ],

    /*
    | Next.js website on-demand revalidation (ISR tags), called after admin content changes.
    */
    'frontend' => [
        'revalidate_url' => env('FRONTEND_REVALIDATE_URL', 'http://127.0.0.1:3100/api/revalidate'),
        'revalidate_secret' => env('FRONTEND_REVALIDATE_SECRET'),
        'revalidate_timeout' => (int) env('FRONTEND_REVALIDATE_TIMEOUT', 2),
    ],

];
