<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Services;

use ContentPulse\Laravel\Models\Content;
use Illuminate\Contracts\Config\Repository as Config;

class SeoBuilder
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forContent(Content $content, string $url): array
    {
        $graphs = [$this->article($content, $url)];

        $faq = $this->faq($content);
        if ($faq !== null) {
            $graphs[] = $faq;
        }

        return $graphs;
    }

    /**
     * @return array<string, mixed>
     */
    private function article(Content $content, string $url): array
    {
        $brandName = (string) $this->config->get('contentpulse.brand.name', 'ContentPulse');
        $logo = $this->config->get('contentpulse.brand.logo');

        $publisher = ['@type' => 'Organization', 'name' => $brandName];
        if (is_string($logo) && $logo !== '') {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }

        $seo = $content->seo ?? [];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $content->title,
            'description' => $seo['meta_description'] ?? $content->excerpt,
            'image' => $content->featured_image,
            'inLanguage' => $content->locale,
            'datePublished' => $content->published_at?->toIso8601String(),
            'dateModified' => $content->content_updated_at?->toIso8601String(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'publisher' => $publisher,
            'author' => $publisher,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faq(Content $content): ?array
    {
        $entities = [];
        foreach ($content->faq ?? [] as $item) {
            if (! is_array($item) || empty($item['question']) || empty($item['answer'])) {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
