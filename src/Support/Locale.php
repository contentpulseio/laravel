<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Support;

final class Locale
{
    /**
     * Return the canonical BCP-47 tag for a stored locale and optional region.
     * Stored source locales may be null; in that case the configured default is used.
     */
    public static function forContent(?string $locale, ?string $region = null): string
    {
        return self::tag($locale, $region)
            ?? self::tag((string) config('contentpulse.localization.default', 'en'))
            ?? 'en';
    }

    public static function tag(?string $locale, ?string $region = null): ?string
    {
        $parts = self::parts($locale);
        if ($parts === null) {
            return null;
        }

        $resolvedRegion = self::normalizeRegion($region)
            ?? $parts['region'];

        $tag = $parts['language'];
        if ($parts['script'] !== null) {
            $tag .= '-'.$parts['script'];
        }
        if ($resolvedRegion !== null) {
            $tag .= '-'.$resolvedRegion;
        }

        return $tag;
    }

    public static function routeSegment(?string $locale, ?string $region = null): string
    {
        $tag = self::forContent($locale, $region);

        if (config('contentpulse.localization.route_mode', 'language') === 'language') {
            return self::language($tag) ?? 'en';
        }

        return strtolower($tag);
    }

    /** @return array{language: string, script: string|null, region: string|null}|null */
    public static function parts(?string $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = preg_split('/[-_]/', trim($value)) ?: [];
        $language = strtolower((string) array_shift($parts));
        if (! preg_match('/^[a-z]{2,3}$/', $language)) {
            return null;
        }

        $script = null;
        $region = null;
        foreach ($parts as $part) {
            if ($script === null && preg_match('/^[a-z]{4}$/i', $part)) {
                $script = ucfirst(strtolower($part));
            } elseif ($region === null && preg_match('/^(?:[a-z]{2}|[0-9]{3})$/i', $part)) {
                $region = strtoupper($part);
            }
        }

        return compact('language', 'script', 'region');
    }

    public static function language(?string $value): ?string
    {
        return self::parts($value)['language'] ?? null;
    }

    public static function region(?string $value): ?string
    {
        return self::parts($value)['region'] ?? null;
    }

    public static function configuredRegion(?string $locale): ?string
    {
        $language = self::language($locale);
        $regions = config('contentpulse.localization.regions', []);
        $configured = is_array($regions) && $language !== null ? ($regions[$language] ?? null) : null;

        return self::normalizeRegion(is_string($configured) ? $configured : null);
    }

    public static function routePattern(): string
    {
        return config('contentpulse.localization.route_mode', 'language') === 'language'
            ? '[a-z]{2,3}'
            : '[a-z]{2,3}(?:-[a-z]{4})?(?:-[a-z]{2}|-[0-9]{3})?';
    }

    public static function isDefault(?string $routeLocale): bool
    {
        $default = self::forContent((string) config('contentpulse.localization.default', 'en'));

        return strtolower(self::forContent($routeLocale)) === strtolower($default);
    }

    public static function normalizeRegion(?string $region): ?string
    {
        if ($region === null || trim($region) === '') {
            return null;
        }

        $parsed = self::parts($region);
        if ($parsed !== null && $parsed['region'] !== null) {
            return $parsed['region'];
        }

        return preg_match('/^(?:[a-z]{2}|[0-9]{3})$/i', trim($region))
            ? strtoupper(trim($region))
            : null;
    }

}
