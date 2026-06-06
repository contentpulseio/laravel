<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Commands;

use ContentPulse\Core\Exceptions\ContentPulseException;
use ContentPulse\Laravel\Services\ContentSyncService;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'contentpulse:sync
        {--locale= : Locale to sync (defaults to all locales)}';

    protected $description = 'Pull published ContentPulse content into the local store.';

    public function handle(ContentSyncService $sync): int
    {
        $locale = $this->option('locale');

        try {
            $count = $sync->syncAll($locale !== null ? (string) $locale : null);
        } catch (ContentPulseException $e) {
            $this->components->error('ContentPulse sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Synced {$count} content item(s).");

        return self::SUCCESS;
    }
}
