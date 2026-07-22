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

    /**
     * Download an upstream image into a stable local path keyed by URL path
     * (query string ignored for the filename so SEO URLs stay stable).
     *
     * Re-downloads when the local file is missing/empty, when ContentPulse's
     * `?v=` cache-buster is newer than the local file mtime, or when $force
     * is true. This is what makes republish/image-regenerate actually replace
     * stale local copies.
     */
    public function localize(?string $url, bool $force = false): ?string
    {
        if ($url === null || $url === '' || ! $this->enabled()) {
            return $url;
        }

        if (! $this->isAbsoluteHttpUrl($url)) {
            return $url;
        }

        $path = $this->targetPath($url);
        $disk = $this->disk();

        if ($this->shouldRedownload($disk, $path, $url, $force)) {
            if (! $this->download($disk, $path, $url) && ! $this->hasValidFile($disk, $path)) {
                return $url;
            }
        }

        return $this->publicUrl($disk, $path);
    }

    /**
     * Download upstream bytes into an already-published local public URL path.
     * Keeps the public URL stable (Image SEO) while replacing file contents when
     * the upstream cache-buster indicates a newer image.
     */
    public function downloadInto(?string $upstreamUrl, string $existingPublicUrl, bool $force = false): bool
    {
        if ($upstreamUrl === null || $upstreamUrl === '' || ! $this->enabled()) {
            return false;
        }

        if (! $this->isAbsoluteHttpUrl($upstreamUrl)) {
            return false;
        }

        $path = $this->diskPathFromPublicUrl($existingPublicUrl);

        if ($path === null) {
            return false;
        }

        $disk = $this->disk();

        if (! $this->shouldRedownload($disk, $path, $upstreamUrl, $force)) {
            return $this->hasValidFile($disk, $path);
        }

        return $this->download($disk, $path, $upstreamUrl);
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

    /**
     * Convert a stored image reference into a browser-safe public URL.
     *
     * Accepts absolute http(s) URLs, already-rooted public paths (/storage/...),
     * and disk-relative paths (media/blog/x.webp) produced by localize().
     *
     * Local disk paths get a `?v={mtime}` cache-buster so CDN edges (Cloudflare)
     * pick up replaced bytes after republish/regenerate without renaming files.
     */
    public function toPublicUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if ($this->isAbsoluteHttpUrl($stored)) {
            return $stored;
        }

        $path = ltrim(explode('?', $stored, 2)[0], '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($stored, '/')) {
            return $this->withCacheBust('/'.ltrim($stored, '/'), $path);
        }

        return $this->withCacheBust($this->disk()->url($path), $path);
    }

    private function withCacheBust(string $url, string $diskPath): string
    {
        $diskPath = ltrim(explode('?', $diskPath, 2)[0], '/');

        if ($diskPath === '' || ! $this->hasValidFile($this->disk(), $diskPath)) {
            return $url;
        }

        try {
            $version = $this->disk()->lastModified($diskPath);
        } catch (Throwable) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$version;
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('contentpulse.images.download', true);
    }

    private function shouldRedownload(FilesystemAdapter $disk, string $path, string $url, bool $force): bool
    {
        if ($force || ! $this->hasValidFile($disk, $path)) {
            return true;
        }

        return $this->isStale($disk, $path, $url);
    }

    /**
     * ContentPulse appends `?v={unix}` to featured_image_url when the content
     * or version changes. Local paths intentionally ignore the query string, so
     * we compare that bust value to the on-disk mtime to detect stale copies.
     */
    private function isStale(FilesystemAdapter $disk, string $path, string $url): bool
    {
        $version = $this->cacheBustVersion($url);

        if ($version === null) {
            return false;
        }

        try {
            return $disk->lastModified($path) < $version;
        } catch (Throwable) {
            return true;
        }
    }

    private function cacheBustVersion(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);

        if (! isset($params['v']) || ! is_numeric($params['v'])) {
            return null;
        }

        return (int) $params['v'];
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

            // Delete first when replacing: PHP-FPM often creates files as
            // www-data while queue/artisan run as forge. Overwriting a
            // www-data-owned 0644 file fails; unlinking via a forge-writable
            // directory then putting a new file succeeds.
            if ($disk->exists($path)) {
                $disk->delete($path);
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

        // Hash the path without ?v= so the public URL stays stable across
        // regenerations; freshness is handled by shouldRedownload()/isStale().
        return $base.'/'.sha1($withoutQuery).'.'.$ext;
    }
}
