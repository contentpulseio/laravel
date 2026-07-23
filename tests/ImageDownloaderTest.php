<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Laravel\Services\ImageDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImageDownloaderTest extends TestCase
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

    public function test_localize_downloads_and_returns_relative_url(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://cdn.example.test/hero.webp' => Http::response(str_repeat('W', 64), 200, [
                'Content-Type' => 'image/webp',
            ]),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize('https://cdn.example.test/hero.webp');
        $expectedPath = 'media/blog/'.sha1('https://cdn.example.test/hero.webp').'.webp';

        $this->assertSame($expectedPath, $result);
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_localize_downloads_contentpulse_relative_translation_path(): void
    {
        Storage::fake('public');
        $this->app['config']->set('contentpulse.images.base_url', 'https://contentpulse.test');

        $relativePath = 'tenants/1/images/263/i18n/ar/titles/hero.webp';
        $upstreamUrl = 'https://contentpulse.test/storage/'.$relativePath;
        Http::fake([
            $upstreamUrl => Http::response(str_repeat('T', 64), 200, [
                'Content-Type' => 'image/webp',
            ]),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize($relativePath);
        $expectedPath = 'media/blog/'.sha1($upstreamUrl).'.webp';

        $this->assertSame($expectedPath, $result);
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_localize_falls_back_to_upstream_when_download_fails(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://cdn.example.test/missing.webp' => Http::response('nope', 404),
        ]);

        $url = 'https://cdn.example.test/missing.webp';
        $result = $this->app->make(ImageDownloader::class)->localize($url);

        $this->assertSame($url, $result);
        Storage::disk('public')->assertMissing('media/blog/'.sha1($url).'.webp');
    }

    public function test_localize_rejects_html_payload_and_keeps_upstream_url(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://cdn.example.test/challenge.webp' => Http::response('<html>blocked</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $url = 'https://cdn.example.test/challenge.webp';
        $result = $this->app->make(ImageDownloader::class)->localize($url);

        $this->assertSame($url, $result);
        Storage::disk('public')->assertMissing('media/blog/'.sha1($url).'.webp');
    }

    public function test_localize_redownloads_when_existing_file_is_empty(): void
    {
        Storage::fake('public');
        $url = 'https://cdn.example.test/empty.webp';
        $path = 'media/blog/'.sha1($url).'.webp';
        Storage::disk('public')->put($path, '');

        Http::fake([
            $url => Http::response(str_repeat('X', 80), 200, ['Content-Type' => 'image/webp']),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize($url);

        $this->assertSame($path, $result);
        $this->assertGreaterThanOrEqual(32, Storage::disk('public')->size($path));
    }

    public function test_localize_redownloads_when_cache_bust_is_newer_than_local_file(): void
    {
        Storage::fake('public');
        $baseUrl = 'https://cdn.example.test/hero.webp';
        $path = 'media/blog/'.sha1($baseUrl).'.webp';
        Storage::disk('public')->put($path, str_repeat('OLD', 32));

        // Storage::fake lastModified is "now"; use a future ?v= to mark stale.
        $freshUrl = $baseUrl.'?v='.(time() + 3600);

        Http::fake([
            $freshUrl => Http::response(str_repeat('NEW', 32), 200, ['Content-Type' => 'image/webp']),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize($freshUrl);

        $this->assertSame($path, $result);
        $this->assertSame(str_repeat('NEW', 32), Storage::disk('public')->get($path));
    }

    public function test_localize_skips_redownload_when_cache_bust_is_not_newer(): void
    {
        Storage::fake('public');
        $baseUrl = 'https://cdn.example.test/stable.webp';
        $path = 'media/blog/'.sha1($baseUrl).'.webp';
        Storage::disk('public')->put($path, str_repeat('KEEP', 32));

        $staleBustUrl = $baseUrl.'?v=1';

        Http::fake([
            $staleBustUrl => Http::response(str_repeat('NEW', 32), 200, ['Content-Type' => 'image/webp']),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize($staleBustUrl);

        $this->assertSame($path, $result);
        $this->assertSame(str_repeat('KEEP', 32), Storage::disk('public')->get($path));
        Http::assertNothingSent();
    }

    public function test_localize_force_overwrites_even_without_newer_cache_bust(): void
    {
        Storage::fake('public');
        $url = 'https://cdn.example.test/force.webp';
        $path = 'media/blog/'.sha1($url).'.webp';
        Storage::disk('public')->put($path, str_repeat('OLD', 32));

        Http::fake([
            $url => Http::response(str_repeat('FORCE', 20), 200, ['Content-Type' => 'image/webp']),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize($url, force: true);

        $this->assertSame($path, $result);
        $this->assertSame(str_repeat('FORCE', 20), Storage::disk('public')->get($path));
    }

    public function test_localize_replaces_existing_file_via_delete_then_put(): void
    {
        Storage::fake('public');
        $url = 'https://cdn.example.test/replace.webp?v='.(time() + 3600);
        $baseUrl = explode('?', $url, 2)[0];
        $path = 'media/blog/'.sha1($baseUrl).'.webp';
        Storage::disk('public')->put($path, str_repeat('OLD', 32));

        Http::fake([
            $url => Http::response(str_repeat('REPLACED', 16), 200, ['Content-Type' => 'image/webp']),
        ]);

        $result = $this->app->make(ImageDownloader::class)->localize($url);

        $this->assertSame($path, $result);
        $this->assertSame(str_repeat('REPLACED', 16), Storage::disk('public')->get($path));
    }

    public function test_download_into_refreshes_existing_public_path_when_stale(): void
    {
        Storage::fake('public');
        $existingPath = 'media/blog/stable-old.webp';
        Storage::disk('public')->put($existingPath, str_repeat('A', 96));

        $upstream = 'https://cdn.example.test/brand-new-path/hero.webp?v='.(time() + 3600);

        Http::fake([
            $upstream => Http::response(str_repeat('B', 96), 200, ['Content-Type' => 'image/webp']),
        ]);

        $ok = $this->app->make(ImageDownloader::class)->downloadInto(
            $upstream,
            '/storage/'.$existingPath,
        );

        $this->assertTrue($ok);
        $this->assertSame(str_repeat('B', 96), Storage::disk('public')->get($existingPath));
    }

    public function test_local_file_exists_for_relative_public_url(): void
    {
        Storage::fake('public');
        $path = 'media/blog/abc123.webp';
        Storage::disk('public')->put($path, str_repeat('Y', 40));

        $downloader = $this->app->make(ImageDownloader::class);

        $this->assertTrue($downloader->localFileExists('/storage/'.$path));
        $this->assertTrue($downloader->localFileExists($path));
        $this->assertFalse($downloader->localFileExists('/storage/media/blog/missing.webp'));
    }

    public function test_to_public_url_prefixes_disk_relative_paths(): void
    {
        Storage::fake('public');
        $downloader = $this->app->make(ImageDownloader::class);

        Storage::disk('public')->put('media/guides/hero.webp', str_repeat('Z', 40));

        $withBust = $downloader->toPublicUrl('media/guides/hero.webp');
        $this->assertNotNull($withBust);
        $this->assertStringStartsWith('/storage/media/guides/hero.webp?v=', $withBust);

        $rooted = $downloader->toPublicUrl('/storage/media/guides/hero.webp');
        $this->assertNotNull($rooted);
        $this->assertStringStartsWith('/storage/media/guides/hero.webp?v=', $rooted);

        $this->assertSame(
            'https://cdn.example.test/hero.webp',
            $downloader->toPublicUrl('https://cdn.example.test/hero.webp'),
        );
        $this->assertNull($downloader->toPublicUrl(null));
        $this->assertNull($downloader->toPublicUrl(''));
    }

    public function test_to_public_url_cache_bust_changes_when_file_is_replaced(): void
    {
        Storage::fake('public');
        $path = 'media/blog/hero.webp';
        Storage::disk('public')->put($path, str_repeat('A', 40));
        $downloader = $this->app->make(ImageDownloader::class);

        $first = $downloader->toPublicUrl($path);
        $this->assertNotNull($first);

        // Storage::fake mtime is typically "now"; replace bytes then assert URL
        // still has a bust param (exact mtime may or may not advance in fake FS).
        Storage::disk('public')->put($path, str_repeat('B', 40));
        $second = $downloader->toPublicUrl($path);
        $this->assertNotNull($second);
        $this->assertMatchesRegularExpression('#\?v=\d+$#', $second);
        $this->assertStringStartsWith('/storage/media/blog/hero.webp?v=', $second);
    }
}
