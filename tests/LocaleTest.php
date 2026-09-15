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

    public function test_source_language_uses_the_configured_regional_default(): void
    {
        config()->set('contentpulse.localization.default', 'en-GB');
        config()->set('contentpulse.localization.route_mode', 'bcp47');

        $this->assertSame('en-GB', Locale::forContent('en'));
        $this->assertSame('en-gb', Locale::routeSegment('en'));
        $this->assertSame('en-CA', Locale::forContent('en', 'CA'));
    }

    public function test_readable_names_include_regions_without_an_app_catalogue(): void
    {
        $this->assertStringContainsString('English', Locale::readableName('en-GB'));
        $this->assertStringContainsString('United Kingdom', Locale::readableName('en-GB'));
        $this->assertStringContainsString('العربية', Locale::readableName('ar-AE'));
    }
}
