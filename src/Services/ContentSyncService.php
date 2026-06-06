<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Services;

use ContentPulse\Core\DTO\ContentFilters;
use ContentPulse\Core\DTO\ContentItem;
use ContentPulse\Http\ContentPulseClient;
use ContentPulse\Laravel\Models\Content;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;

class ContentSyncService
{
    public function __construct(
        private readonly ContentPulseClient $client,
        private readonly Config $config,
    ) {}

    public function syncAll(?string $locale = null): int
    {
        $perPage = (int) $this->config->get('contentpulse.sync.per_page', 50);
        $maxPages = (int) $this->config->get('contentpulse.sync.max_pages', 20);

        $synced = 0;
        $page = 1;

        do {
            $feed = $this->client->getContentFeed(new ContentFilters(
                page: $page,
                perPage: $perPage,
                status: 'published',
                locale: $locale,
            ));

            foreach ($feed->items as $item) {
                $this->upsert($item);
                $synced++;
            }

            $hasMore = $feed->hasMorePages();
            $page++;
        } while ($hasMore && $page <= $maxPages);

        return $synced;
    }

    public function syncById(string $ulid): Content
    {
        return $this->upsert($this->client->getContentById($ulid));
    }

    public function deleteByExternalId(string $ulid): void
    {
        Content::query()->where('external_id', $ulid)->delete();
    }

    public function upsert(ContentItem $item): Content
    {
        $content = Content::query()->updateOrCreate(
            ['external_id' => $item->id],
            [
                'slug' => $item->slug,
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'faq' => $item->faq,
                'rendered_html' => $item->renderedHtml,
                'featured_image' => $this->rewriteImage($item->featuredImage),
                'image_variants' => $this->rewriteImageMap($item->images),
                'seo' => $item->seo?->toArray(),
                'status' => $item->status,
                'content_type' => $item->contentType,
                'locale' => $item->locale,
                'word_count' => $item->wordCount,
                'categories' => $item->categories,
                'tags' => $item->tags,
                'published_at' => $this->date($item->publishedAt),
                'scheduled_at' => $this->date($item->scheduledAt),
                'content_created_at' => $this->date($item->createdAt),
                'content_updated_at' => $this->date($item->updatedAt),
            ],
        );

        return $content;
    }

    private function date(?DateTimeImmutable $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    private function rewriteImage(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        /** @var array<string, string> $rewrites */
        $rewrites = $this->config->get('contentpulse.image_host_rewrites', []);

        return strtr($url, $rewrites);
    }

    /**
     * @param  array<string, mixed>  $images
     * @return array<string, mixed>
     */
    private function rewriteImageMap(array $images): array
    {
        return array_map(
            fn ($value) => is_string($value) ? $this->rewriteImage($value) : $value,
            $images,
        );
    }
}
