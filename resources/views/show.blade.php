@extends(config('contentpulse.layout', 'layouts.app'))

@php
    $seoMeta = $content->seo ?? [];
    $metaTitle = $seoMeta['meta_title'] ?? $content->title;
    $metaDescription = $seoMeta['meta_description'] ?? $content->excerpt;
    $ogTitle = $seoMeta['og_title'] ?? $metaTitle;
    $ogDescription = $seoMeta['og_description'] ?? $metaDescription;
    $twitterTitle = $seoMeta['twitter_title'] ?? $ogTitle;
    $twitterDescription = $seoMeta['twitter_description'] ?? $ogDescription;
    $metaRobots = $seoMeta['meta_robots'] ?? null;

    $metaKeywords = collect($seoMeta['meta_keywords'] ?? [])
        ->map(fn ($k) => trim((string) $k))
        ->filter()
        ->implode(', ');

    $cpImages = collect($content->image_variants ?? [])
        ->filter(fn ($v) => is_array($v) && ! empty($v['url']))
        ->sortBy(fn ($v) => (int) ($v['width'] ?? 0))
        ->values();
    $cpOgImage = ($cpImages->firstWhere('width', '>=', 1200)['url'] ?? null)
        ?? ($cpImages->last()['url'] ?? null)
        ?? $content->featured_image;
    $cpHero = $cpImages->last() ?? ['url' => $content->featured_image];
    $cpSrcset = $cpImages
        ->map(fn ($v) => $v['url'].' '.(int) ($v['width'] ?? 0).'w')
        ->implode(', ');

    $cpTaxonomy = function ($item) {
        $name = is_array($item) ? (string) ($item['name'] ?? '') : (string) $item;
        $slug = is_array($item) && ! empty($item['slug'])
            ? (string) $item['slug']
            : \Illuminate\Support\Str::slug($name);

        return ['name' => $name, 'slug' => $slug];
    };
    $cpCategories = collect($content->categories ?? [])->map($cpTaxonomy)
        ->filter(fn ($item) => $item['name'] !== '')->values();
    $cpTags = collect($content->tags ?? [])->map($cpTaxonomy)
        ->filter(fn ($item) => $item['name'] !== '')->values();
    $cpIndexRoute = \Illuminate\Support\Facades\Route::has('contentpulse.index');
@endphp

@if (config('contentpulse.view.head_directive', 'sections') === 'push_raw')
    @push(config('contentpulse.view.head_target', 'head'))
        @include('contentpulse::partials.head')
    @endpush
@else
    @section('title', $metaTitle)
    @section('meta_description', $metaDescription)
    @section('og_title', $ogTitle)
    @section('og_description', $ogDescription)
    @section('og_type', 'article')
    @section('twitter_title', $twitterTitle)
    @section('twitter_description', $twitterDescription)
    @if (! empty($metaKeywords))
        @section('meta_keywords', $metaKeywords)
    @endif
    @section('meta_extra')
        @if (! empty($metaRobots))
            <meta name="robots" content="{{ $metaRobots }}">
        @endif
        @if (! empty($cpOgImage))
            <meta property="og:image" content="{{ $cpOgImage }}">
            <meta name="twitter:image" content="{{ $cpOgImage }}">
        @endif
    @endsection

    @push(config('contentpulse.view.structured_data_target', 'structured-data'))
        @foreach ($jsonLd as $graph)
            <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
    @endpush
@endif

@section('content')
    @include('contentpulse::partials.styles')

    <article class="cp-article">
        @if ($cpCategories->isNotEmpty() || $content->published_at)
            <div class="cp-article__meta">
                @foreach ($cpCategories as $category)
                    @if ($cpIndexRoute)
                        <a href="{{ route('contentpulse.index', ['category' => $category['slug']]) }}" class="cp-cat">{{ $category['name'] }}</a>
                    @else
                        <span class="cp-cat">{{ $category['name'] }}</span>
                    @endif
                @endforeach
                @if ($content->published_at)
                    <time datetime="{{ $content->published_at->toIso8601String() }}">{{ $content->published_at->format('M j, Y') }}</time>
                @endif
            </div>
        @endif

        <h1 class="cp-article__title">{{ $content->title }}</h1>

        @if (! empty($content->featured_image))
            <figure class="cp-article__hero-image">
                <img
                    src="{{ $cpHero['url'] ?? $content->featured_image }}"
                    @if (! empty($cpSrcset)) srcset="{{ $cpSrcset }}" sizes="(max-width: 768px) 100vw, 768px" @endif
                    @if (! empty($cpHero['width'])) width="{{ (int) $cpHero['width'] }}" @endif
                    @if (! empty($cpHero['height'])) height="{{ (int) $cpHero['height'] }}" @endif
                    alt="{{ $content->title }}" loading="eager" decoding="async">
            </figure>
        @endif

        <div class="cp-article__body">
            {!! $content->rendered_html !!}
        </div>

        @if ($cpTags->isNotEmpty())
            <div class="cp-article__tags">
                @foreach ($cpTags as $tag)
                    @if ($cpIndexRoute)
                        <a href="{{ route('contentpulse.index', ['tag' => $tag['slug']]) }}" class="cp-tag">{{ $tag['name'] }}</a>
                    @else
                        <span class="cp-tag">{{ $tag['name'] }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    </article>
@endsection
