<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Core\DTO\ContentItem;
use ContentPulse\Http\ContentPulseClient;
use ContentPulse\Laravel\Models\Content;
use ContentPulse\Laravel\Services\ContentSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ContentSyncPreserveImagesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('contentpulse.images.download', true);
        $app['config']->set('contentpulse.images.disk', 'public');
        $app['config']->set('contentpulse.images.path', 'media/blog');
        $app['config']->set('contentpulse.images.relative_url', true);
        $app['config']->set('contentpulse.images.preserve_existing_urls', true);
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => '/storage',
            'visibility' => 'public',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $migration = require __DIR__.'/../database/migrations/add_author_and_body_to_contentpulse_contents_table.php.stub';
        $migration->up();
    }

    public function test_sync_preserves_existing_featured_url_when_local_file_exists(): void
    {
        Storage::fake('public');

        $existingPath = 'media/blog/stable-old.webp';
        $existingUrl = '/storage/'.$existingPath;
        Storage::disk('public')->put($existingPath, str_repeat('A', 96));

        Content::query()->create([
            'external_id' => '01TESTPRESERVE0000000000001',
            'slug' => 'preserve-me',
            'title' => 'Preserve Me',
            'status' => 'published',
            'featured_image' => $existingUrl,
            'image_variants' => [],
        ]);

        // No ?v= → preserve URL and leave bytes untouched (not stale).
        $newUpstream = 'https://cdn.example.test/brand-new-path/hero.webp';
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTPRESERVE0000000000001',
            'slug' => 'preserve-me',
            'title' => 'Preserve Me',
            'excerpt' => 'Hello',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'en',
            'word_count' => 10,
            'featured_image_url' => $newUpstream,
            'image_variants' => [],
            'categories' => [],
            'tags' => [],
            'faq' => [],
            'published_at' => '2026-07-15T12:00:00Z',
            'created_at' => '2026-07-15T12:00:00Z',
            'updated_at' => '2026-07-15T12:00:00Z',
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);

        Http::fake([
            $newUpstream => Http::response(str_repeat('B', 96), 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById('01TESTPRESERVE0000000000001');

        $content = Content::query()->where('external_id', '01TESTPRESERVE0000000000001')->first();
        $this->assertNotNull($content);
        $this->assertSame($existingUrl, $content->featured_image);
        Storage::disk('public')->assertMissing('media/blog/'.sha1($newUpstream).'.webp');
        $this->assertSame(str_repeat('A', 96), Storage::disk('public')->get($existingPath));
        Http::assertNothingSent();
    }

    public function test_sync_refreshes_preserved_file_bytes_when_cache_bust_is_newer(): void
    {
        Storage::fake('public');

        $existingPath = 'media/blog/stable-old.webp';
        $existingUrl = '/storage/'.$existingPath;
        Storage::disk('public')->put($existingPath, str_repeat('A', 96));

        Content::query()->create([
            'external_id' => '01TESTPRESERVE0000000000003',
            'slug' => 'refresh-me',
            'title' => 'Refresh Me',
            'status' => 'published',
            'featured_image' => $existingUrl,
            'image_variants' => [],
        ]);

        $newUpstream = 'https://cdn.example.test/brand-new-path/hero.webp?v='.(time() + 3600);
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTPRESERVE0000000000003',
            'slug' => 'refresh-me',
            'title' => 'Refresh Me',
            'excerpt' => 'Hello',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'en',
            'word_count' => 10,
            'featured_image_url' => $newUpstream,
            'image_variants' => [],
            'categories' => [],
            'tags' => [],
            'faq' => [],
            'published_at' => '2026-07-15T12:00:00Z',
            'created_at' => '2026-07-15T12:00:00Z',
            'updated_at' => '2026-07-15T12:00:00Z',
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);

        Http::fake([
            $newUpstream => Http::response(str_repeat('B', 96), 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById('01TESTPRESERVE0000000000003');

        $content = Content::query()->where('external_id', '01TESTPRESERVE0000000000003')->first();
        $this->assertNotNull($content);
        $this->assertSame($existingUrl, $content->featured_image);
        $this->assertSame(str_repeat('B', 96), Storage::disk('public')->get($existingPath));
        Storage::disk('public')->assertMissing('media/blog/'.sha1(explode('?', $newUpstream, 2)[0]).'.webp');
    }

    public function test_sync_rewrites_featured_url_when_local_file_missing(): void
    {
        Storage::fake('public');

        Content::query()->create([
            'external_id' => '01TESTPRESERVE0000000000002',
            'slug' => 'repair-me',
            'title' => 'Repair Me',
            'status' => 'published',
            'featured_image' => '/storage/media/blog/gone.webp',
            'image_variants' => [],
        ]);

        $upstream = 'https://cdn.example.test/repair-me.webp';
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTPRESERVE0000000000002',
            'slug' => 'repair-me',
            'title' => 'Repair Me',
            'excerpt' => 'Hello',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'en',
            'word_count' => 10,
            'featured_image_url' => $upstream,
            'image_variants' => [],
            'categories' => [],
            'tags' => [],
            'faq' => [],
            'published_at' => '2026-07-15T12:00:00Z',
            'created_at' => '2026-07-15T12:00:00Z',
            'updated_at' => '2026-07-15T12:00:00Z',
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);

        Http::fake([
            $upstream => Http::response(str_repeat('C', 96), 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById('01TESTPRESERVE0000000000002');

        $content = Content::query()->where('external_id', '01TESTPRESERVE0000000000002')->first();
        $this->assertNotNull($content);
        $this->assertSame('/storage/media/blog/'.sha1($upstream).'.webp', $content->featured_image);
        Storage::disk('public')->assertExists('media/blog/'.sha1($upstream).'.webp');
    }

    public function test_sync_uses_variant_path_when_legacy_variant_url_is_missing(): void
    {
        Storage::fake('public');
        $this->app['config']->set('contentpulse.images.base_url', 'https://contentpulse.test');

        $legacyUrl = 'https://contentpulse.test/storage/media/blog/removed-thumbnail.webp';
        $tenantPath = 'tenants/1/images/263/i18n/es/variants/thumbnail/article-RmNjRnMN.webp';
        $tenantUrl = 'https://contentpulse.test/storage/'.$tenantPath;
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTVARIANTFALLBACK000001',
            'slug' => 'variant-fallback',
            'title' => 'Variant fallback',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'es',
            'image_variants' => [
                'thumbnail' => [
                    'url' => $legacyUrl,
                    'path' => $tenantPath,
                    'width' => 320,
                    'height' => 175,
                ],
            ],
            'categories' => [],
            'tags' => [],
            'faq' => [],
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);
        Http::fake([
            $legacyUrl => Http::response('missing', 404),
            $tenantUrl => Http::response(str_repeat('V', 64), 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById('01TESTVARIANTFALLBACK000001');

        $content = Content::query()->where('external_id', '01TESTVARIANTFALLBACK000001')->first();
        $this->assertNotNull($content);
        $expectedPath = 'media/blog/'.sha1($tenantUrl).'.webp';
        $this->assertSame('/storage/'.$expectedPath, $content->image_variants['thumbnail']['url'] ?? null);
        $this->assertSame($tenantPath, $content->image_variants['thumbnail']['path'] ?? null);
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_sync_downloads_chart_images_in_structured_body_and_rendered_html(): void
    {
        Storage::fake('public');

        $chartPath = '/storage/content/42/charts/ai-adoption.png';
        $chartUrl = 'https://contentpulse.io'.$chartPath;
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTCHART000000000000001',
            'slug' => 'chart-images',
            'title' => 'Chart images',
            'status' => 'published',
            'content_type' => 'article',
            'body' => [[
                'type' => 'chart',
                'data' => [
                    'stat_group_id' => 'ai-adoption',
                    'image_url' => $chartPath,
                    'image_variants' => [],
                ],
            ]],
            'rendered_html' => '<figure><img src="'.$chartPath.'" alt="AI adoption"></figure>',
            'categories' => [],
            'tags' => [],
            'faq' => [],
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);
        Http::fake([
            $chartUrl => Http::response(str_repeat('P', 64), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById('01TESTCHART000000000000001');

        $path = 'media/blog/'.sha1($chartUrl).'.png';
        $public = '/storage/'.$path;
        $content = Content::query()->where('external_id', '01TESTCHART000000000000001')->first();
        $this->assertNotNull($content);
        $this->assertSame($public, $content->body[0]['data']['image_url']);
        $this->assertStringContainsString('src="'.$public.'"', (string) $content->rendered_html);
        $this->assertStringContainsString('src="'.$public, $content->content);
        Storage::disk('public')->assertExists($path);
    }

    public function test_content_accessor_rewrites_legacy_disk_relative_img_srcs(): void
    {
        Storage::fake('public');
        $path = 'media/blog/legacy-chart.png';
        Storage::disk('public')->put($path, str_repeat('P', 64));

        $content = Content::query()->create([
            'external_id' => '01TESTLEGACYCHART00000001',
            'slug' => 'legacy-chart-src',
            'title' => 'Legacy chart src',
            'status' => 'published',
            'rendered_html' => '<figure><img src="'.$path.'" alt="Legacy"></figure>',
            'published_at' => now(),
        ]);

        $this->assertStringContainsString('src="/storage/'.$path, $content->content);
        $this->assertStringNotContainsString('src="media/blog/', $content->content);
    }

    public function test_sync_builds_rendered_html_for_structured_translated_chart_body(): void
    {
        Storage::fake('public');

        $chartPath = '/storage/content/42/charts/i18n/es/adoption.png';
        $chartUrl = 'https://contentpulse.io'.$chartPath;
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTTRANSLATEDCHART00001',
            'slug' => 'translated-chart',
            'title' => 'Adopción por plataforma',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'es',
            'body' => [[
                'type' => 'chart',
                'data' => [
                    'title' => 'Adopción por plataforma',
                    'image_url' => $chartPath,
                    'image_alt' => 'Gráfico de adopción',
                    'data' => [
                        ['label' => 'Sanidad', 'value' => 42],
                    ],
                ],
            ]],
            'categories' => [],
            'tags' => [],
            'faq' => [],
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);
        Http::fake([
            $chartUrl => Http::response(str_repeat('T', 64), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById('01TESTTRANSLATEDCHART00001');

        $content = Content::query()->where('external_id', '01TESTTRANSLATEDCHART00001')->first();
        $this->assertNotNull($content);
        $this->assertStringContainsString('Adopción por plataforma', (string) $content->rendered_html);
        $this->assertStringContainsString('/storage/media/blog/'.sha1($chartUrl).'.png', (string) $content->rendered_html);
        Storage::disk('public')->assertExists('media/blog/'.sha1($chartUrl).'.png');
    }

    public function test_translation_sync_does_not_reuse_existing_source_chart_url(): void
    {
        Storage::fake('public');
        $this->app['config']->set('contentpulse.images.base_url', 'https://contentpulse.test');

        $sharedPath = 'media/blog/shared-chart.png';
        Storage::disk('public')->put($sharedPath, str_repeat('english-chart', 4));

        Content::query()->create([
            'external_id' => '01TESTTRANSLATEDSHARED00001__ar',
            'slug' => 'shared-chart',
            'title' => 'العنوان',
            'status' => 'published',
            'locale' => 'ar',
            'body' => [[
                'type' => 'chart',
                'data' => [
                    'stat_group_id' => 'sector-share',
                    'image_url' => '/storage/'.$sharedPath,
                ],
            ]],
        ]);

        $chartPath = '/storage/content/42/charts/i18n/ar/sector-share.png';
        $chartUrl = 'https://contentpulse.test'.$chartPath;
        $item = ContentItem::fromApiResponse([
            'id' => '01TESTTRANSLATEDSHARED00001__ar',
            'slug' => 'shared-chart',
            'title' => 'العنوان',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'ar',
            'body' => [[
                'type' => 'chart',
                'data' => [
                    'stat_group_id' => 'sector-share',
                    'image_url' => $chartPath,
                ],
            ]],
            'parent_external_id' => '01TESTTRANSLATEDSHARED00001',
            'categories' => [],
            'tags' => [],
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);
        Http::fake([
            $chartUrl => Http::response(str_repeat('arabic-chart', 4), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->app->make(ContentSyncService::class)->syncById($item->id);

        $content = Content::query()->where('external_id', $item->id)->first();
        $this->assertNotNull($content);
        $expectedPath = 'media/blog/'.sha1($chartUrl).'.png';
        $this->assertSame('/storage/'.$expectedPath, $content->body[0]['data']['image_url']);
        $this->assertSame(str_repeat('english-chart', 4), Storage::disk('public')->get($sharedPath));
        $this->assertSame(str_repeat('arabic-chart', 4), Storage::disk('public')->get($expectedPath));
    }
}
