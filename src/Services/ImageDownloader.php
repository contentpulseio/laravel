<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Throwable;

class ImageDownloader
{
    /**
     * Reject empty/tiny payloads that would otherwise leave a relative URL
     * pointing at a useless (or later-deleted) file.
     */
    private const MIN_BYTES = 32;

    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly Config $config,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function localize(?string $url): ?string
    {
        if ($url === null || $url === '' || ! $this->enabled()) {
            return $url;
        }

        if (! $this->isAbsoluteHttpUrl($url)) {
            return $url;
        }

        $path = $this->targetPath($url);
        $disk = $this->disk();

        if (! $this->hasValidFile($disk, $path) && ! $this->download($disk, $path, $url)) {
            return $url;
        }

        return $this->publicUrl($disk, $path);
    }

    /**
     * True when a localized public URL (relative or absolute disk URL) still
     * resolves to a non-empty file on the configured image disk.
     */
    public function localFileExists(?string $publicUrl): bool
    {
        if ($publicUrl === null || $publicUrl === '') {
            return false;
        }

        $path = $this->diskPathFromPublicUrl($publicUrl);

        if ($path === null) {
            return false;
        }

        return $this->hasValidFile($this->disk(), $path);
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('contentpulse.images.download', true);
    }

    private function download(FilesystemAdapter $disk, string $path, string $url): bool
    {
        try {
            $response = Http::timeout($this->timeout())->get($url);

            if (! $response->successful()) {
                $this->logger?->warning('ContentPulse: image download failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $body = $response->body();
            $contentType = strtolower((string) $response->header('Content-Type'));

            if (strlen($body) < self::MIN_BYTES) {
                $this->logger?->warning('ContentPulse: image download too small', [
                    'url' => $url,
                    'bytes' => strlen($body),
                ]);

                return false;
            }

            if (str_contains($contentType, 'text/html') || str_starts_with(ltrim($body), '<')) {
                $this->logger?->warning('ContentPulse: image download returned HTML', [
                    'url' => $url,
                    'content_type' => $contentType !== '' ? $contentType : null,
                ]);

                return false;
            }

            if (! $disk->put($path, $body)) {
                $this->logger?->warning('ContentPulse: image download put failed', [
                    'url' => $url,
                    'path' => $path,
                ]);

                return false;
            }

            if (! $this->hasValidFile($disk, $path)) {
                $disk->delete($path);
                $this->logger?->warning('ContentPulse: image missing after put', [
                    'url' => $url,
                    'path' => $path,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $this->logger?->warning('ContentPulse: image download threw', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function hasValidFile(FilesystemAdapter $disk, string $path): bool
    {
        if (! $disk->exists($path)) {
            return false;
        }

        try {
            return $disk->size($path) >= self::MIN_BYTES;
        } catch (Throwable) {
            return false;
        }
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function diskPathFromPublicUrl(string $publicUrl): ?string
    {
        $withoutQuery = explode('?', $publicUrl, 2)[0];
        $path = parse_url($withoutQuery, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $withoutQuery;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $base = trim((string) $this->config->get('contentpulse.images.path', 'media/blog'), '/');

        if ($base !== '' && ! str_starts_with($path, $base.'/')) {
            return null;
        }

        return $path !== '' ? $path : null;
    }

    private function publicUrl(FilesystemAdapter $disk, string $path): string
    {
        $url = $disk->url($path);

        if (! (bool) $this->config->get('contentpulse.images.relative_url', true)) {
            return $url;
        }

        // Store disk-relative paths (e.g. media/blog/x.webp) so host apps can
        // safely call asset('storage/'.$path) without double-prefixing /storage/.
        return ltrim($path, '/');
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystem->disk(
            (string) $this->config->get('contentpulse.images.disk', 'public'),
        );

        return $disk;
    }

    private function timeout(): int
    {
        return (int) $this->config->get('contentpulse.timeout', 30);
    }

    private function targetPath(string $url): string
    {
        $base = trim((string) $this->config->get('contentpulse.images.path', 'media/blog'), '/');

        $withoutQuery = explode('?', $url, 2)[0];
        $ext = pathinfo((string) parse_url($withoutQuery, PHP_URL_PATH), PATHINFO_EXTENSION);
        $ext = $ext !== '' ? mb_strtolower($ext) : 'jpg';

        return $base.'/'.sha1($withoutQuery).'.'.$ext;
    }
}
