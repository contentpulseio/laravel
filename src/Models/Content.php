<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        return $query->where(function ($inner) use ($column, $slug) {
            $inner->where($column, 'like', '%"slug":"'.$slug.'"%')
                ->orWhereJsonContains($column, $slug);
        });
    }
}
