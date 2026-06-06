@once
    @php
        $cpTopOffset = config('contentpulse.view.top_offset', '2rem');
        $cpMaxWidth = config('contentpulse.view.max_width', '760px');
        $cpCtaBg = config('contentpulse.cta.background', '#7c3aed');
        $cpCtaText = config('contentpulse.cta.text_color', '#ffffff');
        $cpCtaRadius = config('contentpulse.cta.radius', '6px');
        $cpCtaShadow = config('contentpulse.cta.shadow', 'none');
    @endphp
    <style>
        .cp-article, .cp-index {
            --cp-fg: #1a1a1a;
            --cp-muted: #5b6470;
            --cp-border: #e5e7eb;
            --cp-bg-soft: #f7f8fa;
            --cp-accent: #2563eb;
            --cp-radius: 12px;
            --cp-top-offset: {{ $cpTopOffset }};
            --cp-max-width: {{ $cpMaxWidth }};
            --cp-cta-bg: {{ $cpCtaBg }};
            --cp-cta-color: {{ $cpCtaText }};
            --cp-cta-radius: {{ $cpCtaRadius }};
            --cp-cta-shadow: {{ $cpCtaShadow }};
            max-width: var(--cp-max-width);
            margin: 0 auto;
            padding: var(--cp-top-offset) 1.25rem 4rem;
            color: var(--cp-fg);
            font-size: 1.0625rem;
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
        }

        .cp-index { --cp-max-width: 1080px; }

        .cp-article :where(h1, h2, h3) { line-height: 1.25; color: var(--cp-fg); }
        .cp-article__title { font-size: 2.25rem; font-weight: 800; line-height: 1.2; margin: 0 0 1.5rem; }
        .cp-article__meta { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin: 0 0 1rem; font-size: .85rem; color: var(--cp-muted); }
        .cp-article__meta .cp-cat { background: var(--cp-bg-soft); color: var(--cp-accent); border: 1px solid var(--cp-border); padding: .15rem .6rem; border-radius: 9999px; font-weight: 600; text-decoration: none; transition: background .15s ease; }
        a.cp-cat:hover { background: #eef2ff; }
        .cp-article__meta time { letter-spacing: .01em; }
        .cp-article__tags { display: flex; flex-wrap: wrap; gap: .5rem; margin: 2.5rem 0 0; padding-top: 1.5rem; border-top: 1px solid var(--cp-border); }
        .cp-article__tags .cp-tag { font-size: .8rem; color: var(--cp-muted); background: var(--cp-bg-soft); border: 1px solid var(--cp-border); padding: .2rem .65rem; border-radius: 9999px; text-decoration: none; transition: background .15s ease; }
        a.cp-tag:hover { background: #eef2ff; color: var(--cp-fg); }
        .cp-article__tags .cp-tag::before { content: "#"; opacity: .6; }
        .cp-article h2 { font-size: 1.6rem; margin: 2.5rem 0 1rem; }
        .cp-article h3 { font-size: 1.25rem; margin: 2rem 0 .75rem; }
        .cp-article p { margin: 0 0 1.1rem; text-align: justify; text-wrap: pretty; hyphens: auto; }
        .cp-article a { color: var(--cp-accent); }

        .cp-article ul,
        .cp-article ol { margin: 0 0 1.1rem; padding-left: 1.5rem; }
        .cp-article ul { list-style: disc; }
        .cp-article ol { list-style: decimal; }
        .cp-article li { margin: .35rem 0; }
        .cp-article li::marker { color: var(--cp-accent); }

        .cp-article table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: .97rem;
        }
        .cp-article th,
        .cp-article td {
            border: 1px solid var(--cp-border);
            padding: .6rem .85rem;
            text-align: left;
            vertical-align: top;
        }
        .cp-article thead th { background: var(--cp-bg-soft); font-weight: 600; }
        .cp-article tbody tr:nth-child(even) { background: #fcfcfd; }

        .cp-article a[style*="#7c3aed"] {
            background: var(--cp-cta-bg) !important;
            color: var(--cp-cta-color) !important;
            border-radius: var(--cp-cta-radius) !important;
            box-shadow: var(--cp-cta-shadow) !important;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .cp-article a[style*="#7c3aed"]:hover { transform: translateY(-1px); }

        .cp-article__hero-image img,
        .cp-card__media img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: var(--cp-radius);
        }
        .cp-article__hero-image { margin: 0 0 2rem; }

        .cp-index__title { font-size: 2rem; margin: 0 0 2rem; }
        .cp-index__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .cp-card { display: flex; flex-direction: column; border: 1px solid var(--cp-border); border-radius: var(--cp-radius); overflow: hidden; text-decoration: none; color: inherit; transition: box-shadow .15s ease; }
        .cp-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .cp-card__body { padding: 1.25rem; }
        .cp-card__title { font-size: 1.2rem; margin: 0 0 .5rem; }
        .cp-card__excerpt { color: var(--cp-muted); font-size: .95rem; margin: 0 0 .75rem; }
        .cp-card__date { color: var(--cp-muted); font-size: .85rem; }
        .cp-index__empty { text-align: center; color: var(--cp-muted); padding: 4rem 0; }
        .cp-index__filter { color: var(--cp-muted); font-size: .95rem; margin: 0 0 1.5rem; }
        .cp-index__filter a { margin-left: .5rem; color: var(--cp-accent); }
        .cp-index__pagination { margin-top: 2.5rem; }
    </style>
@endonce
