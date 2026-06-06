@extends(config('contentpulse.layout', 'layouts.app'))

@php
    $brandName = config('contentpulse.brand.name', config('app.name', 'Resources'));
@endphp

@section('title', $brandName)

@section('content')
    @include('contentpulse::partials.styles')

    <div class="cp-index">
        <header class="cp-index__header">
            <h1 class="cp-index__title">{{ $brandName }}</h1>
            @if (! empty($activeCategory) || ! empty($activeTag))
                <p class="cp-index__filter">
                    Filtered by {{ ! empty($activeCategory) ? 'category' : 'tag' }}:
                    <strong>{{ $activeCategory ?? $activeTag }}</strong>
                    <a href="{{ route('contentpulse.index') }}">Clear</a>
                </p>
            @endif
        </header>

        @if ($contents->isEmpty())
            <div class="cp-index__empty">
                <p>No articles found.</p>
            </div>
        @else
            <div class="cp-index__grid">
                @foreach ($contents as $item)
                    <a class="cp-card" href="{{ route('contentpulse.show', $item->slug) }}">
                        @if (! empty($item->featured_image))
                            <div class="cp-card__media">
                                <img src="{{ $item->featured_image }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
                            </div>
                        @endif
                        <div class="cp-card__body">
                            <h2 class="cp-card__title">{{ $item->title }}</h2>
                            @if (! empty($item->excerpt))
                                <p class="cp-card__excerpt">{{ $item->excerpt }}</p>
                            @endif
                            @if ($item->published_at)
                                <time class="cp-card__date" datetime="{{ $item->published_at->toIso8601String() }}">
                                    {{ $item->published_at->format('M j, Y') }}
                                </time>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="cp-index__pagination">
                {{ $contents->links() }}
            </div>
        @endif
    </div>
@endsection
