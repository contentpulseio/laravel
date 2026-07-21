<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Laravel\Models\Content;

class RenderingTest extends TestCase
{
    private function makeContent(array $overrides = []): Content
    {
        return Content::query()->create(array_merge([
            'external_id' => '01HZZZEXTERNAL',
            'slug' => 'getting-started',
            'title' => 'Getting Started Guide',
            'excerpt' => 'A short intro to the topic.',
            'faq' => [
                ['question' => 'Is it free?', 'answer' => 'Yes, totally free.'],
            ],
            'rendered_html' => '<h2>Getting Started Guide</h2>'
                .'<p>Everything you need to begin.</p>'
                .'<p>First paragraph body.</p>'
                .'<h3>Is it free?</h3>'
                .'<p>Yes, totally free.</p>',
            'featured_image' => 'https://cdn.example.test/hero.jpg',
            'seo' => [
                'meta_title' => 'Getting Started | Example',
                'meta_description' => 'Learn the basics quickly.',
                'meta_keywords' => ['onboarding', 'setup guide'],
            ],
            'status' => 'published',
            'content_type' => 'guide',
            'locale' => 'en',
            'published_at' => now(),
            'content_updated_at' => now(),
        ], $overrides));
    }

    public function test_show_route_prints_server_rendered_html(): void
    {
        $this->makeContent();

        $response = $this->get(route('contentpulse.show', 'getting-started'));

        $response->assertOk();
        $response->assertSee('Getting Started Guide', false);
        $response->assertSee('Everything you need to begin.', false);
        $response->assertSee('First paragraph body.', false);
        $response->assertSee('Is it free?', false);
        $response->assertSee('Yes, totally free.', false);
    }

    public function test_show_route_emits_seo_meta_and_json_ld(): void
    {
        $this->makeContent();

        $response = $this->get(route('contentpulse.show', 'getting-started'));

        $response->assertOk();
        $response->assertSee('Learn the basics quickly.', false);
        $response->assertSee('og:title', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"Article"', false);
        $response->assertSee('"@type":"FAQPage"', false);
    }

    public function test_keywords_fill_host_slot_without_duplicating(): void
    {
        $this->makeContent();

        $response = $this->get(route('contentpulse.show', 'getting-started'));

        $response->assertOk();
        $response->assertSee('content="onboarding, setup guide"', false);
        $this->assertSame(
            1,
            substr_count($response->getContent(), '<meta name="keywords"'),
            'keywords meta tag must render exactly once'
        );
    }

    public function test_show_route_uses_host_layout(): void
    {
        $this->makeContent();

        $response = $this->get(route('contentpulse.show', 'getting-started'));

        $response->assertSee('<!DOCTYPE html>', false);
        $response->assertSee('<title>Getting Started | Example</title>', false);
    }

    public function test_draft_content_is_not_rendered(): void
    {
        $this->makeContent([
            'slug' => 'hidden',
            'external_id' => '01HZZZHIDDEN',
            'status' => 'draft',
        ]);

        $this->get(route('contentpulse.show', 'hidden'))->assertNotFound();
    }

    public function test_index_route_lists_published_content(): void
    {
        $this->makeContent();
        $this->makeContent([
            'slug' => 'second-post',
            'external_id' => '01HZZZSECOND',
            'title' => 'Second Post',
        ]);

        $response = $this->get(route('contentpulse.index'));

        $response->assertOk();
        $response->assertSee('Getting Started Guide', false);
        $response->assertSee('Second Post', false);
    }

    public function test_show_route_resolves_disk_relative_featured_image_to_storage_url(): void
    {
        $this->makeContent([
            'featured_image' => 'media/guides/hero.webp',
            'image_variants' => [
                'og' => [
                    'url' => 'media/guides/og.webp',
                    'width' => 1200,
                    'height' => 630,
                ],
                'thumbnail' => [
                    'url' => 'media/guides/thumb.webp',
                    'width' => 320,
                    'height' => 175,
                ],
            ],
        ]);

        $response = $this->get(route('contentpulse.show', 'getting-started'));

        $response->assertOk();
        $response->assertSee('src="/storage/media/guides/og.webp"', false);
        $response->assertSee('/storage/media/guides/thumb.webp 320w', false);
        $response->assertSee('content="'.url('/storage/media/guides/og.webp').'"', false);
        $response->assertSee('"image":"'.url('/storage/media/guides/hero.webp').'"', false);
        $response->assertDontSee('src="media/guides/', false);
    }
}
