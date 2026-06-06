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

`contentpulse:install` publishes the config and migration. The service provider
is auto-discovered.

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

## Rendering in your own views

Query the local model directly:

```php
use ContentPulse\Laravel\Models\Content;

$content = Content::published()->where('slug', $slug)->firstOrFail();

echo $content->rendered_html;
```

## Artisan

```bash
php artisan contentpulse:sync --locale=en
```

Backfills the local store from the API (useful for the initial import).

## License

MIT
