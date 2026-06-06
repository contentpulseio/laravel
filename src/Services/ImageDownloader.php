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

        $path = $this->targetPath($url);
        $disk = $this->disk();

        if (! $disk->exists($path)) {
            try {
                $response = Http::timeout($this->timeout())->get($url);

                if (! $response->successful()) {
                    $this->logger?->warning('ContentPulse: image download failed', [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);

                    return $url;
                }

                $disk->put($path, $response->body());
            } catch (Throwable $e) {
                $this->logger?->warning('ContentPulse: image download threw', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                return $url;
            }
        }

        return $this->publicUrl($disk, $path);
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('contentpulse.images.download', false);
    }

    private function publicUrl(FilesystemAdapter $disk, string $path): string
    {
        $url = $disk->url($path);

        if (! (bool) $this->config->get('contentpulse.images.relative_url', true)) {
            return $url;
        }

        $parts = parse_url($url);
        $relative = $parts['path'] ?? $url;

        if (isset($parts['query'])) {
            $relative .= '?'.$parts['query'];
        }

        return $relative;
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

        $withoutQuery = strtok($url, '?') ?: $url;
        $ext = pathinfo((string) parse_url($withoutQuery, PHP_URL_PATH), PATHINFO_EXTENSION);
        $ext = $ext !== '' ? mb_strtolower($ext) : 'jpg';

        return $base.'/'.sha1($withoutQuery).'.'.$ext;
    }
}
