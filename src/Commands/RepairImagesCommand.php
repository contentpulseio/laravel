<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Commands;

use ContentPulse\Core\Exceptions\ContentPulseException;
use ContentPulse\Laravel\Models\Content;
use ContentPulse\Laravel\Services\ContentSyncService;
use ContentPulse\Laravel\Services\ImageDownloader;
use Illuminate\Console\Command;

class RepairImagesCommand extends Command
{
    protected $signature = 'contentpulse:repair-images
        {--external-id= : Repair a single ContentPulse ULID}
        {--force : Re-sync images even when local files already exist}
        {--dry-run : List rows that need repair without re-syncing}';

    protected $description = 'Re-fetch ContentPulse items and re-download featured images / variants when local files are missing.';

    public function handle(ContentSyncService $sync, ImageDownloader $images): int
    {
        if (! (bool) config('contentpulse.images.download', true)) {
            $this->components->warn('CONTENTPULSE_DOWNLOAD_IMAGES is disabled; nothing to repair.');

            return self::SUCCESS;
        }

        $query = Content::query()->orderBy('id');
        $externalId = $this->option('external-id');

        if (is_string($externalId) && $externalId !== '') {
            $query->where('external_id', $externalId);
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $scanned = 0;
        $needsRepair = 0;
        $repaired = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($query->cursor() as $content) {
            $scanned++;
            $missing = $this->missingLocalUrls($content, $images);
            $shouldRepair = $force || $missing !== [];

            if (! $shouldRepair) {
                $skipped++;

                continue;
            }

            $needsRepair++;
            $label = $content->external_id.' ('.$content->slug.')';
            $reason = $missing !== [] ? implode(', ', $missing) : 'force re-sync';

            if ($dryRun) {
                $this->line("would repair {$label}: {$reason}");

                continue;
            }

            try {
                $sync->syncById($content->external_id);
                $fresh = $content->fresh() ?? $content;
                $stillMissing = $this->missingLocalUrls($fresh, $images);

                if ($stillMissing !== []) {
                    $failed++;
                    $this->components->error("repair incomplete for {$label}: ".implode(', ', $stillMissing));

                    continue;
                }

                $repaired++;
                $this->components->info("repaired {$label}");
            } catch (ContentPulseException $e) {
                $failed++;
                $this->components->error("repair failed for {$label}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->components->info(sprintf(
            'scanned=%d needs_repair=%d repaired=%d failed=%d skipped=%d dry_run=%s',
            $scanned,
            $needsRepair,
            $repaired,
            $failed,
            $skipped,
            $dryRun ? 'yes' : 'no',
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function missingLocalUrls(Content $content, ImageDownloader $images): array
    {
        $missing = [];
        $candidates = [];

        if (is_string($content->featured_image) && $content->featured_image !== '') {
            $candidates['featured'] = $content->featured_image;
        }

        foreach ($content->image_variants ?? [] as $key => $variant) {
            if (is_string($variant) && $variant !== '') {
                $candidates[(string) $key] = $variant;
            } elseif (is_array($variant) && isset($variant['url']) && is_string($variant['url']) && $variant['url'] !== '') {
                $candidates[(string) $key] = $variant['url'];
            }
        }

        foreach ($candidates as $key => $url) {
            if ($this->isAbsoluteHttpUrl($url)) {
                $missing[] = $key;

                continue;
            }

            if (! $images->localFileExists($url)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
