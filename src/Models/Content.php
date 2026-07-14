<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $external_id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property array<int, array{question: string, answer: string}>|null $faq
 * @property string|null $rendered_html
 * @property string|null $featured_image
 * @property array<string, mixed>|null $image_variants
 * @property array<string, mixed>|null $seo
 * @property string|null $status
 * @property string|null $content_type
 * @property string|null $locale
 * @property int|null $word_count
 * @property array<string, mixed>|null $categories
 * @property array<string, mixed>|null $tags
 * @property string|null $author_name
 * @property string|null $author_job_title
 * @property string|null $author_bio
 * @property string|null $author_avatar_url
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $content_created_at
 * @property Carbon|null $content_updated_at
 */
class Content extends Model
{
    protected $fillable = [
        'external_id',
        'slug',
        'title',
        'excerpt',
        'faq',
        'rendered_html',
        'featured_image',
        'image_variants',
        'seo',
        'status',
        'content_type',
        'locale',
        'word_count',
        'categories',
        'tags',
        'author_name',
        'author_job_title',
        'author_bio',
        'author_avatar_url',
        'published_at',
        'scheduled_at',
        'content_created_at',
        'content_updated_at',
    ];

    protected $casts = [
        'faq' => 'array',
        'image_variants' => 'array',
        'seo' => 'array',
        'categories' => 'array',
        'tags' => 'array',
        'word_count' => 'integer',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'content_created_at' => 'datetime',
        'content_updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('contentpulse.table', 'contentpulse_contents');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->orderByDesc('published_at');
    }

    public function scopeWhereCategory($query, string $slug)
    {
        return $this->scopeWhereTaxonomy($query, 'categories', $slug);
    }

    public function scopeWhereTag($query, string $slug)
    {
        return $this->scopeWhereTaxonomy($query, 'tags', $slug);
    }

    protected function scopeWhereTaxonomy($query, string $column, string $slug)
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->where(function ($inner) use ($column, $slug) {
                $inner->whereRaw(
                    $column.' LIKE ?',
                    ['%"slug":"'.$slug.'"%']
                )->orWhereRaw(
                    $column.' LIKE ?',
                    ['%"'.$slug.'"%']
                );
            });
        }

        return $query->where(function ($inner) use ($column, $slug) {
            $inner->whereRaw(
                'JSON_SEARCH(`'.$column.'`, "one", ?, NULL, \'$[*].slug\') IS NOT NULL',
                [$slug]
            )->orWhereJsonContains($column, $slug);
        });
    }

    public function getContentAttribute(): string
    {
        return (string) ($this->rendered_html ?? '');
    }

    public function getReadTimeAttribute(): int
    {
        $words = (int) ($this->word_count ?? 0);

        return $words > 0 ? max(1, (int) ceil($words / 200)) : 5;
    }

    public function getViewsAttribute(): int
    {
        return 0;
    }

    public function getUserAttribute(): ?object
    {
        return null;
    }

    public function getMetaTitleAttribute(): ?string
    {
        return $this->seoValue('meta_title');
    }

    public function getMetaDescriptionAttribute(): ?string
    {
        return $this->seoValue('meta_description') ?: $this->excerpt;
    }

    public function getMetaKeywordsAttribute(): string
    {
        $keywords = $this->seo['meta_keywords'] ?? [];

        if (is_array($keywords)) {
            return implode(', ', $keywords);
        }

        return (string) $keywords;
    }

    /**
     * @return Collection<int, object>
     */
    public function getCategoriesAttribute($value): Collection
    {
        return $this->taxonomy($value);
    }

    /**
     * @return Collection<int, object>
     */
    public function getTagsAttribute($value): Collection
    {
        return $this->taxonomy($value);
    }

    private function seoValue(string $key): ?string
    {
        $value = $this->seo[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return Collection<int, object>
     */
    private function taxonomy($value): Collection
    {
        $items = is_string($value) ? json_decode($value, true) : $value;

        return collect(is_array($items) ? $items : [])
            ->map(function ($item) {
                if (is_array($item)) {
                    $name = $item['name'] ?? $item['slug'] ?? '';
                    $slug = $item['slug'] ?? Str::slug((string) $name);
                } else {
                    $name = (string) $item;
                    $slug = Str::slug($name);
                }

                return (object) ['name' => $name, 'slug' => $slug];
            })
            ->filter(fn ($item) => $item->name !== '')
            ->values();
    }
}
