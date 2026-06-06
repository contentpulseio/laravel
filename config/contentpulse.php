<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your ContentPulse API key. Generate one from your ContentPulse dashboard
    | under Settings -> API Keys. Sent as the X-API-Key header on every request.
    |
    */
    'api_key' => env('CONTENTPULSE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The ContentPulse API base URL. Leave null to use the SDK default
    | (https://contentpulse.io). Override for self-hosted or staging.
    |
    */
    'base_url' => env('CONTENTPULSE_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for an API response.
    |
    */
    'timeout' => (int) env('CONTENTPULSE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Shared secret used to verify the HMAC-SHA256 signature on incoming
    | ContentPulse webhooks (X-Webhook-Signature header). When empty, the
    | webhook endpoint responds 503 and rejects all requests.
    |
    */
    'webhook_secret' => env('CONTENTPULSE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Local Persistence
    |--------------------------------------------------------------------------
    |
    | Table that synced content is upserted into. Publish and run the package
    | migration (php artisan vendor:publish --tag="contentpulse-migrations")
    | before enabling the publishing/rendering features.
    |
    */
    'table' => env('CONTENTPULSE_TABLE', 'contentpulse_contents'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The package ships a webhook receiver plus public resource (article)
    | routes. Set `enabled` to false to register nothing and wire your own.
    | `middleware` is applied to the public resource routes only; the webhook
    | route always uses the signature-verification middleware.
    |
    */
    'routes' => [
        'enabled' => (bool) env('CONTENTPULSE_ROUTES_ENABLED', true),
        'prefix' => env('CONTENTPULSE_ROUTES_PREFIX', 'resources'),
        'webhook_path' => env('CONTENTPULSE_WEBHOOK_PATH', 'webhooks/contentpulse'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Host Layout
    |--------------------------------------------------------------------------
    |
    | The Blade layout the package views extend. Your application owns all
    | chrome (head, nav, footer). The layout must yield a `content` section
    | and may consume `@yield('title')`, `@yield('meta_description')`, the
    | `head` stack, etc. See the README for the contract.
    |
    */
    'layout' => env('CONTENTPULSE_LAYOUT', 'layouts.app'),

    /*
    |--------------------------------------------------------------------------
    | Synchronisation
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'per_page' => (int) env('CONTENTPULSE_SYNC_PER_PAGE', 50),
        'max_pages' => (int) env('CONTENTPULSE_SYNC_MAX_PAGES', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding (SEO / JSON-LD)
    |--------------------------------------------------------------------------
    |
    | Used to build Article JSON-LD. `logo` is resolved through url() when set.
    |
    */
    'brand' => [
        'name' => env('CONTENTPULSE_BRAND_NAME', env('APP_NAME', 'ContentPulse')),
        'logo' => env('CONTENTPULSE_BRAND_LOGO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Host Rewrites
    |--------------------------------------------------------------------------
    |
    | Map of upstream image hosts to replacements applied to synced image URLs.
    | Useful for local development against a containerised ContentPulse.
    |
    */
    'image_host_rewrites' => [
        // 'http://contentpulse.test:8080' => 'http://localhost:8080',
    ],
];
