<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Laravel\Support\Locale;

class LocaleTest extends TestCase
{
    public function test_bcp47_tags_are_canonicalised_and_routed_lowercase(): void
    {
        config()->set('contentpulse.localization.route_mode', 'bcp47');

        $this->assertSame('en-GB', Locale::tag('en', 'gb'));
        $this->assertSame('zh-Hant-TW', Locale::tag('zh-Hant-TW'));
        $this->assertSame('zh-hant-tw', Locale::routeSegment('zh-Hant-TW'));
    }

    public function test_language_routes_can_resolve_a_configured_region(): void
    {
        config()->set('contentpulse.localization.route_mode', 'language');
        config()->set('contentpulse.localization.regions', ['en' => 'GB']);

        $this->assertSame('en-GB', Locale::forContent('en'));
        $this->assertSame('en', Locale::routeSegment('en'));
        $this->assertSame('GB', Locale::configuredRegion('en'));
    }
}
