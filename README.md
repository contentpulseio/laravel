# ContentPulse for Laravel

Laravel integration for [ContentPulse.io](https://contentpulse.io). Receives
content webhooks, stores a local copy of the published content, and renders it
through your own layout. Once content is delivered, your app serves it from its
own database with no runtime dependency on ContentPulse.

## Requirements

- PHP `^8.2`
- Laravel `^10 | ^11 | ^12`

## Installation

```bash
composer require contentpulseio/laravel
php artisan contentpulse:install
php artisan migrate
```

The package is published on [Packagist](https://packagist.org/packages/contentpulseio/laravel),
so the command above works out of the box. `contentpulse:install` publishes the
config and migration; the service provider is auto-discovered.

If you install from a private fork instead of Packagist, add the repository to
your `composer.json` before requiring:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/contentpulseio/laravel" }
]
```

Set your credentials in `.env`:

```dotenv
CONTENTPULSE_API_KEY=your-api-key
CONTENTPULSE_WEBHOOK_SECRET=your-webhook-secret
# optional
CONTENTPULSE_BASE_URL=https://contentpulse.io
CONTENTPULSE_TIMEOUT=30
```

## How it works

1. Register the webhook endpoint (`webhooks/contentpulse`) in your ContentPulse
   dashboard. Incoming requests are verified against `CONTENTPULSE_WEBHOOK_SECRET`.
2. On `content.*` events the package fetches the item and upserts it into the
   `contentpulse_contents` table.
3. Published content is served from the local store via the bundled routes:
   - `GET /resources` — index
   - `GET /resources/{slug}` — single item

Set your layout so rendered content slots into your site chrome:

```dotenv
CONTENTPULSE_LAYOUT=layouts.app
```

## Webhooks

The package ships a ready-to-use webhook receiver — no controller or middleware
to write in your app.

- **Endpoint:** `POST /webhooks/contentpulse` (override with `CONTENTPULSE_WEBHOOK_PATH`).
- **Signature:** every request is verified by the `VerifyContentPulseSignature`
  middleware. It computes `hash_hmac('sha256', <raw request body>, CONTENTPULSE_WEBHOOK_SECRET)`
  and compares it (constant-time) to the `X-Webhook-Signature` header.
  - Secret not set → `503`
  - Missing/invalid signature → `401`
  - Valid → `200 {"status":"ok"}`
- **Handled events** (from the `event` field or `X-Webhook-Event` header, with
  `data.content_id` as the ContentPulse ULID):
  - `content.created`, `content.updated`, `content.published` → fetch the item
    from the API and upsert it into `contentpulse_contents`
  - `content.deleted` → remove the local row

Register the endpoint in your ContentPulse dashboard (Webhooks) pointing at
`https://your-app.test/webhooks/contentpulse`, subscribe to the `content.*`
events, and set the signing secret to match `CONTENTPULSE_WEBHOOK_SECRET`.
Published content then syncs automatically; `contentpulse:sync` is only needed
for the initial backfill.

## Rendering in your own views

Query the local model directly:

```php
use ContentPulse\Laravel\Models\Content;

$content = Content::published()->where('slug', $slug)->firstOrFail();

echo $content->rendered_html;
```

## Customizing the design

The bundled Blade views are fully overridable. Publish them into your app to
own the markup and styling:

```bash
php artisan vendor:publish --tag=contentpulse-views
```

This copies the views to `resources/views/vendor/contentpulse/`:

- `index.blade.php` — list page
- `show.blade.php` — single item
- `partials/head.blade.php` — SEO meta tags / JSON-LD
- `partials/styles.blade.php` — base CSS

Laravel automatically loads the published copies instead of the package
defaults, so edit them freely. Delete a file to fall back to the package
version. Package updates never overwrite your published views.

## Artisan

```bash
php artisan contentpulse:sync --locale=en
```

Backfills the local store from the API (useful for the initial import).

## License

MIT
