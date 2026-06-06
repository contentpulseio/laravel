@extends(config('contentpulse.layout', 'layouts.app'))

@php
    $seoMeta = $content->seo ?? [];
    $metaTitle = $seoMeta['meta_title'] ?? $content->title;
    $metaDescription = $seoMeta['meta_description'] ?? $content->excerpt;
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDescription)

@push('head')
    @if (! empty($metaDescription))
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if (! empty($content->featured_image))
        <meta property="og:image" content="{{ $content->featured_image }}">
    @endif
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:type" content="article">
    @foreach ($jsonLd as $graph)
        <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach
@endpush

@section('content')
    @include('contentpulse::partials.styles')

    <article class="cp-article">
        @if (! empty($content->featured_image))
            <figure class="cp-article__hero-image">
                <img src="{{ $content->featured_image }}" alt="{{ $content->title }}" loading="eager" decoding="async">
            </figure>
        @endif

        <div class="cp-article__body">
            {!! $content->rendered_html !!}
        </div>
    </article>
@endsection
