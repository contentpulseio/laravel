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
        $existing = Content::query()->where('external_id', $item->id)->first();

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
            // Editorial update only — never Laravel Content.updated_at from the API
            // (admin/import touches bump that without changing article body).
            'content_updated_at' => $this->date($this->editorialUpdatedAt($item)),
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
            $attributes['featured_image'] = $this->resolveImageUrl(
                $item->featuredImage,
                $existing?->featured_image,
            );
        }
        if ($item->images !== []) {
            $existingVariants = is_array($existing?->image_variants) ? $existing->image_variants : [];
            $attributes['image_variants'] = $this->rewriteImageMap($item->images, $existingVariants);
        }

        $author = $item->raw['website_author'] ?? null;
        if (is_array($author) && ! empty($author['name'])) {
            $attributes['author'] = [
                'name' => $author['name'],
                'expertise_title' => $author['job_title'] ?? null,
                'bio' => $author['bio'] ?? null,
                'avatar_url' => $author['avatar_url'] ?? null,
            ];
        }

        $body = $item->raw['body'] ?? $item->raw['current_version']['body'] ?? [];
        if (! empty($body) && is_array($body)) {
            $attributes['body'] = $body;
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
     * Prefer API last_refreshed_at (editorial refresh / SC optimized_at), then
     * published_at. Never use Laravel row updated_at from the payload.
     */
    private function editorialUpdatedAt(ContentItem $item): ?DateTimeImmutable
    {
        $raw = $item->raw['last_refreshed_at'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $raw)
                ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $raw)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        return $item->updatedAt ?? $item->publishedAt;
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

    /**
     * Prefer the already-published SC public URL when its local file still
     * exists. Prevents Google Image SEO churn when ContentPulse changes the
     * upstream absolute URL (which would otherwise produce a new
     * media/blog/{sha1(url)} path).
     */
    private function resolveImageUrl(string $upstream, ?string $existingPublicUrl): ?string
    {
        if ($this->shouldPreserveExistingUrl($existingPublicUrl)) {
            return $existingPublicUrl;
        }

        return $this->rewriteImage($upstream);
    }

    private function shouldPreserveExistingUrl(?string $existingPublicUrl): bool
    {
        if ($existingPublicUrl === null || $existingPublicUrl === '') {
            return false;
        }

        if (! (bool) $this->config->get('contentpulse.images.preserve_existing_urls', true)) {
            return false;
        }

        return $this->images->localFileExists($existingPublicUrl);
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
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function rewriteImageMap(array $images, array $existing = []): array
    {
        foreach ($images as $key => $value) {
            $existingUrl = $this->existingVariantUrl($existing[$key] ?? null);

            if (is_string($value)) {
                $images[$key] = $this->resolveImageUrl($value, $existingUrl);
            } elseif (is_array($value) && isset($value['url']) && is_string($value['url'])) {
                $value['url'] = $this->resolveImageUrl($value['url'], $existingUrl);
                $images[$key] = $value;
            }
        }

        return $images;
    }

    private function existingVariantUrl(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && isset($value['url']) && is_string($value['url']) && $value['url'] !== '') {
            return $value['url'];
        }

        return null;
    }
}
