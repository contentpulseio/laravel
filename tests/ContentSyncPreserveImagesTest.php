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
        $this->assertSame('media/blog/'.sha1($upstream).'.webp', $content->featured_image);
        Storage::disk('public')->assertExists('media/blog/'.sha1($upstream).'.webp');
    }
}
