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

    public function test_language_routes_remain_language_only_without_region_data(): void
    {
        config()->set('contentpulse.localization.route_mode', 'language');

        $this->assertSame('en', Locale::forContent('en'));
        $this->assertSame('en', Locale::routeSegment('en'));
    }
}
