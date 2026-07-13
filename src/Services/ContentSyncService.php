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
        private readonly ImageDownloader $images,
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
                // The list endpoint omits heavy fields (rendered_html,
                // featured_image, etc.). Fetch the full item so synced
                // records always contain displayable content.
                $full = $this->client->getContentById($item->id);
                $this->upsert($full);
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
        $attributes = [
            'slug' => $item->slug,
            'title' => $item->title,
            'excerpt' => $item->excerpt,
            'seo' => $this->seo($item),
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
        ];

        // Only overwrite rich-media fields when the API actually returns data.
        // The list/feed endpoint omits rendered_html, featured_image, and
        // image_variants to keep responses compact; blindly writing null would
        // erase values that a previous single-content sync (webhook) stored.
        if ($item->faq !== []) {
            $attributes['faq'] = $item->faq;
        }
        if ($item->renderedHtml !== null && $item->renderedHtml !== '') {
            $attributes['rendered_html'] = $item->renderedHtml;
        }
        if ($item->featuredImage !== null && $item->featuredImage !== '') {
            $attributes['featured_image'] = $this->rewriteImage($item->featuredImage);
        }
        if ($item->images !== []) {
            $attributes['image_variants'] = $this->rewriteImageMap($item->images);
        }

        $content = Content::query()->updateOrCreate(
            ['external_id' => $item->id],
            $attributes,
        );

        return $content;
    }

    private function date(?DateTimeImmutable $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function seo(ContentItem $item): ?array
    {
        $seo = $item->seo?->toArray() ?? [];

        if (empty($seo['meta_keywords'])) {
            $keywords = $item->raw['keywords']
                ?? ($item->raw['current_version']['meta_keywords'] ?? null);

            if (is_array($keywords) && $keywords !== []) {
                $seo['meta_keywords'] = array_values(array_filter(
                    array_map(static fn ($k) => is_string($k) ? trim($k) : '', $keywords),
                    static fn (string $k) => $k !== '',
                ));
            }
        }

        return $seo === [] ? null : $seo;
    }

    private function rewriteImage(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        /** @var array<string, string> $rewrites */
        $rewrites = $this->config->get('contentpulse.image_host_rewrites', []);

        return $this->images->localize(strtr($url, $rewrites));
    }

    /**
     * Image variants arrive as nested maps ({"og": {"url": ..., "path": ...}}).
     * Rewrite string values directly and the `url` field of nested variants.
     *
     * @param  array<string, mixed>  $images
     * @return array<string, mixed>
     */
    private function rewriteImageMap(array $images): array
    {
        foreach ($images as $key => $value) {
            if (is_string($value)) {
                $images[$key] = $this->rewriteImage($value);
            } elseif (is_array($value) && isset($value['url']) && is_string($value['url'])) {
                $value['url'] = $this->rewriteImage($value['url']);
                $images[$key] = $value;
            }
        }

        return $images;
    }
}
