<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Http\Controllers;

use ContentPulse\Laravel\Models\Content;
use ContentPulse\Laravel\Services\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResourceController
{
    public function index(Request $request): View
    {
        $perPage = (int) config('contentpulse.sync.per_page', 20);

        $category = $this->filterSlug($request->query('category'));
        $tag = $this->filterSlug($request->query('tag'));

        $contents = Content::query()
            ->published()
            ->when($category !== null, fn ($query) => $query->whereCategory($category))
            ->when($tag !== null, fn ($query) => $query->whereTag($tag))
            ->paginate($perPage)
            ->withQueryString();

        return view('contentpulse::index', [
            'contents' => $contents,
            'activeCategory' => $category,
            'activeTag' => $tag,
        ]);
    }

    private function filterSlug(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::slug($value);
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
