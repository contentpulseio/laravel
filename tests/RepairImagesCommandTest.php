<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Core\DTO\ContentItem;
use ContentPulse\Http\ContentPulseClient;
use ContentPulse\Laravel\Models\Content;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;

class RepairImagesCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('contentpulse.images.download', true);
        $app['config']->set('contentpulse.images.disk', 'public');
        $app['config']->set('contentpulse.images.path', 'media/blog');
        $app['config']->set('contentpulse.images.relative_url', true);
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

    public function test_repair_images_command_is_registered(): void
    {
        $this->assertArrayHasKey('contentpulse:repair-images', Artisan::all());
    }

    public function test_dry_run_reports_missing_local_featured_image(): void
    {
        Storage::fake('public');

        Content::query()->create([
            'external_id' => '01TESTARTICLE00000000000001',
            'slug' => 'sample-article',
            'title' => 'Sample',
            'status' => 'published',
            'featured_image' => '/storage/media/blog/missing.webp',
            'image_variants' => [],
        ]);

        $this->artisan('contentpulse:repair-images', ['--dry-run' => true])
            ->expectsOutputToContain('would repair 01TESTARTICLE00000000000001 (sample-article): featured')
            ->assertSuccessful();
    }

    public function test_repair_resyncs_and_redownloads_missing_image(): void
    {
        Storage::fake('public');

        Content::query()->create([
            'external_id' => '01TESTARTICLE00000000000002',
            'slug' => 'needs-repair',
            'title' => 'Needs Repair',
            'status' => 'published',
            'featured_image' => '/storage/media/blog/gone.webp',
            'image_variants' => [],
        ]);

        $upstream = 'https://cdn.example.test/needs-repair.webp';
        $localPath = 'media/blog/'.sha1($upstream).'.webp';

        $item = ContentItem::fromApiResponse([
            'id' => '01TESTARTICLE00000000000002',
            'slug' => 'needs-repair',
            'title' => 'Needs Repair',
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
        $client->shouldReceive('getContentById')
            ->once()
            ->with('01TESTARTICLE00000000000002')
            ->andReturn($item);
        $this->app->instance(ContentPulseClient::class, $client);

        Http::fake([
            $upstream => Http::response(str_repeat('Z', 96), 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->artisan('contentpulse:repair-images', [
            '--external-id' => '01TESTARTICLE00000000000002',
        ])->assertSuccessful();

        Storage::disk('public')->assertExists($localPath);

        $content = Content::query()->where('external_id', '01TESTARTICLE00000000000002')->first();
        $this->assertNotNull($content);
        $this->assertSame($localPath, $content->featured_image);
    }
}
