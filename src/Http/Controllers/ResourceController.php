<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Http\Controllers;

use ContentPulse\Laravel\Models\Content;
use ContentPulse\Laravel\Services\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ResourceController
{
    public function index(Request $request): View
    {
        $perPage = (int) config('contentpulse.sync.per_page', 20);

        $contents = Content::query()
            ->published()
            ->paginate($perPage)
            ->withQueryString();

        return view('contentpulse::index', [
            'contents' => $contents,
        ]);
    }

    public function show(string $slug, SeoBuilder $seo): View
    {
        $content = Content::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('contentpulse::show', [
            'content' => $content,
            'jsonLd' => $seo->forContent($content, request()->url()),
        ]);
    }
}
