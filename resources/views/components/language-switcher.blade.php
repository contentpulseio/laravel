@props(['links' => [], 'current' => null, 'label' => 'Read in'])
@php
    $items = collect($links)
        ->filter(fn ($href, $tag): bool => is_string($tag) && is_string($href) && $href !== '')
        ->unique(fn ($href, $tag): string => strtolower($tag))
        ->all();
@endphp
@if (count($items) > 1)
    <details data-contentpulse-language-switcher class="relative shrink-0">
        <summary class="inline-flex min-h-[44px] cursor-pointer list-none items-center gap-2 rounded-xl border border-[#B9D7C5] px-3 py-2 text-sm font-semibold text-[#2D6A4F] transition hover:bg-[#F1F8F4] [&::-webkit-details-marker]:hidden">
            <span aria-hidden="true">◎</span>
            <span>{{ $label }}: {{ \ContentPulse\Laravel\Support\Locale::readableName($current) }}</span>
            <span aria-hidden="true">⌄</span>
        </summary>
        <div class="absolute right-0 top-full z-30 mt-2 max-h-72 w-56 overflow-y-auto rounded-xl border border-[#dfe2e9] bg-white py-1 shadow-xl">
            @foreach ($items as $tag => $href)
                @php($isCurrent = $current !== null && strtolower((string) $tag) === strtolower((string) $current))
                <a href="{{ $href }}" hreflang="{{ \ContentPulse\Laravel\Support\Locale::forContent($tag) }}" @if ($isCurrent) aria-current="page" @endif class="block px-4 py-2.5 text-sm {{ $isCurrent ? 'bg-[#F1F8F4] font-bold text-[#2D6A4F]' : 'text-[#64708a] hover:bg-[#F1F8F4]' }}">
                    {{ \ContentPulse\Laravel\Support\Locale::readableName($tag) }}
                </a>
            @endforeach
        </div>
    </details>
@endif
