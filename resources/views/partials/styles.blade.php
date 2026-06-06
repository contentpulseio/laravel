@once
    <style>
        .cp-article, .cp-index {
            --cp-fg: #1a1a1a;
            --cp-muted: #5b6470;
            --cp-border: #e5e7eb;
            --cp-bg-soft: #f7f8fa;
            --cp-accent: #2563eb;
            --cp-radius: 12px;
            max-width: 760px;
            margin: 0 auto;
            padding: 2rem 1.25rem 4rem;
            color: var(--cp-fg);
            font-size: 1.0625rem;
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
        }

        .cp-index { max-width: 1080px; }

        .cp-article :where(h1, h2, h3) { line-height: 1.25; color: var(--cp-fg); }
        .cp-article h2 { font-size: 1.6rem; margin: 2.5rem 0 1rem; }
        .cp-article h3 { font-size: 1.25rem; margin: 2rem 0 .75rem; }
        .cp-article p { margin: 0 0 1.1rem; }
        .cp-article a { color: var(--cp-accent); }

        .cp-article__hero-image img,
        .cp-card__media img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: var(--cp-radius);
        }
        .cp-article__hero-image { margin: 0 0 2rem; }

        /* index cards */
        .cp-index__title { font-size: 2rem; margin: 0 0 2rem; }
        .cp-index__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .cp-card { display: flex; flex-direction: column; border: 1px solid var(--cp-border); border-radius: var(--cp-radius); overflow: hidden; text-decoration: none; color: inherit; transition: box-shadow .15s ease; }
        .cp-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .cp-card__body { padding: 1.25rem; }
        .cp-card__title { font-size: 1.2rem; margin: 0 0 .5rem; }
        .cp-card__excerpt { color: var(--cp-muted); font-size: .95rem; margin: 0 0 .75rem; }
        .cp-card__date { color: var(--cp-muted); font-size: .85rem; }
        .cp-index__empty { text-align: center; color: var(--cp-muted); padding: 4rem 0; }
        .cp-index__pagination { margin-top: 2.5rem; }
    </style>
@endonce
