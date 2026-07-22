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

    /*
    |--------------------------------------------------------------------------
    | Local Image Download
    |--------------------------------------------------------------------------
    |
    | When `download` is enabled, synced featured images and variant URLs are
    | fetched and stored on the configured filesystem disk, and the app-served
    | public URL is persisted instead of the upstream ContentPulse URL. Run
    | `php artisan storage:link` once when using the default `public` disk.
    | Downloads are content-addressed (cache-busting query params ignored) so
    | re-syncing reuses existing files. Failed downloads fall back to the
    | upstream URL so rendering never breaks.
    |
    | - path: storage location relative to the disk root. Kept generic by
    |   default so the public URL does not reveal the content source.
    | - relative_url: when true (default), the stored public URL is rooted
    |   (e.g. /storage/media/blog/...) so images resolve on any host/port the
    |   app is served from, independent of APP_URL. Set false to persist the
    |   absolute disk URL (required for off-origin disks such as S3/CDN).
    |
    */
    'images' => [
        'download' => (bool) env('CONTENTPULSE_DOWNLOAD_IMAGES', true),
        'disk' => env('CONTENTPULSE_IMAGE_DISK', 'public'),
        'path' => env('CONTENTPULSE_IMAGE_PATH', 'media/blog'),
        'relative_url' => (bool) env('CONTENTPULSE_IMAGE_RELATIVE_URL', true),
        // When true (default), sync keeps the existing public image URL if the
        // local file still exists — avoids Google Image SEO churn when the
        // upstream ContentPulse URL path changes on refresh. Local file bytes
        // still refresh when upstream ?v= is newer than on-disk mtime (or when
        // contentpulse:repair-images --force is used).
        'preserve_existing_urls' => (bool) env('CONTENTPULSE_PRESERVE_IMAGE_URLS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | View Appearance
    |--------------------------------------------------------------------------
    |
    | Light-touch presentation knobs for the bundled article/index views. These
    | feed CSS custom properties in the styles partial so consuming apps can
    | tune them without overriding the whole stylesheet.
    |
    | - top_offset: padding above the article. Bump this when your layout has a
    |   fixed/sticky header so content is not hidden underneath it (e.g. '6rem').
    | - max_width:  reading width of the article column.
    |
    */
    'view' => [
        'top_offset' => env('CONTENTPULSE_VIEW_TOP_OFFSET', '2rem'),
        'max_width' => env('CONTENTPULSE_VIEW_MAX_WIDTH', '760px'),

        /*
         | How the package injects SEO <meta>/JSON-LD into your layout's <head>.
         |
         |   'sections' (default) -> fills your layout's conventional SEO @yield()s:
         |       title, meta_description, og_title, og_description, og_type,
         |       twitter_title, twitter_description, meta_extra (robots + image).
         |       JSON-LD is pushed to @stack(structured_data_target). No duplicate
         |       tags because the host renders each yield once.
         |
         |   'push_raw' -> @push()es a self-contained <meta>/JSON-LD block into
         |       @stack(head_target). Use for headless/minimal layouts that have
         |       @stack('head') and do NOT already emit og/twitter defaults.
         */
        'head_directive' => env('CONTENTPULSE_HEAD_DIRECTIVE', 'sections'),
        'head_target' => env('CONTENTPULSE_HEAD_TARGET', 'head'),
        'structured_data_target' => env('CONTENTPULSE_STRUCTURED_DATA_TARGET', 'structured-data'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dark Mode
    |--------------------------------------------------------------------------
    |
    | Override the default light-mode CSS variables for dark backgrounds. The
    | package emits a `@media (prefers-color-scheme: dark)` block that applies
    | these values. Set `strategy` to:
    |
    |   'media'  (default) -> respects the OS/browser prefers-color-scheme.
    |   'class'  -> uses `.dark .cp-article` (e.g. Tailwind class strategy).
    |   'none'   -> skip the dark block entirely (host app owns all theming).
    |
    | Each colour can be overridden individually; null values keep the
    | light-mode default.
    |
    */
    'dark' => [
        'strategy' => env('CONTENTPULSE_DARK_STRATEGY', 'media'),
        'fg' => env('CONTENTPULSE_DARK_FG', '#e4e4e7'),
        'muted' => env('CONTENTPULSE_DARK_MUTED', '#a1a1aa'),
        'border' => env('CONTENTPULSE_DARK_BORDER', '#3f3f46'),
        'bg_soft' => env('CONTENTPULSE_DARK_BG_SOFT', '#27272a'),
        'accent' => env('CONTENTPULSE_DARK_ACCENT', '#818cf8'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Call-to-Action Button
    |--------------------------------------------------------------------------
    |
    | Override the colours of CTA buttons embedded in rendered content so they
    | match your brand. Applied via CSS custom properties in the styles partial.
    |
    */
    'cta' => [
        'background' => env('CONTENTPULSE_CTA_BG', '#7c3aed'),
        'text_color' => env('CONTENTPULSE_CTA_TEXT', '#ffffff'),
        'radius' => env('CONTENTPULSE_CTA_RADIUS', '6px'),
        'shadow' => env('CONTENTPULSE_CTA_SHADOW', 'none'),
    ],
];
