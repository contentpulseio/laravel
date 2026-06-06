{{-- SEO meta + JSON-LD for a single content item. Included from show.blade.php
     inside either a @push or @section block (see contentpulse.view.head_directive). --}}
@if (! empty($metaDescription))
    <meta name="description" content="{{ $metaDescription }}">
@endif
@if (! empty($metaKeywords))
    <meta name="keywords" content="{{ $metaKeywords }}">
@endif
@if (! empty($metaRobots))
    <meta name="robots" content="{{ $metaRobots }}">
@endif
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:type" content="article">
@if (! empty($cpOgImage))
    <meta property="og:image" content="{{ $cpOgImage }}">
@endif
<meta name="twitter:card" content="{{ ! empty($cpOgImage) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $twitterTitle }}">
<meta name="twitter:description" content="{{ $twitterDescription }}">
@if (! empty($cpOgImage))
    <meta name="twitter:image" content="{{ $cpOgImage }}">
@endif
@foreach ($jsonLd as $graph)
    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
