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

        $this->assertSame(
            '/storage/media/guides/hero.webp',
            $downloader->toPublicUrl('media/guides/hero.webp'),
        );
        $this->assertSame(
            '/storage/media/guides/hero.webp',
            $downloader->toPublicUrl('/storage/media/guides/hero.webp'),
        );
        $this->assertSame(
            'https://cdn.example.test/hero.webp',
            $downloader->toPublicUrl('https://cdn.example.test/hero.webp'),
        );
        $this->assertNull($downloader->toPublicUrl(null));
        $this->assertNull($downloader->toPublicUrl(''));
    }
}
