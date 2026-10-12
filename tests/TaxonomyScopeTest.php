<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Laravel\Models\Content;

final class TaxonomyScopeTest extends TestCase
{
    private function content(string $slug, array $categories, array $tags = []): void
    {
        Content::query()->create([
            'external_id' => 'ext-'.$slug,
            'slug' => $slug,
            'title' => 'Title '.$slug,
            'status' => 'published',
            'locale' => 'en',
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function test_category_and_tag_scopes_match_taxonomy_objects_and_legacy_strings(): void
    {
        $this->content('fees', [['id' => '01A', 'name' => 'Fees and Costs', 'slug' => 'fees-and-costs']], [['name' => 'VAT', 'slug' => 'vat']]);
        $this->content('legacy', ['fees-and-costs']);
        $this->content('other', [['name' => 'Regulation', 'slug' => 'regulation']]);

        $this->assertSame(['fees', 'legacy'], Content::query()->whereCategory('fees-and-costs')->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['fees'], Content::query()->whereTag('vat')->pluck('slug')->all());
        $this->assertSame([], Content::query()->whereCategory('missing')->pluck('slug')->all());
    }

    /**
     * MySQL and PostgreSQL must get portable JSON containment, never MySQL-only
     * JSON_SEARCH with backticks (which PostgreSQL rejects).
     */
    public function test_category_scope_compiles_to_json_containment_on_mysql_and_postgres(): void
    {
        config([
            'database.connections.cp_mysql' => ['driver' => 'mysql', 'database' => 'unused', 'prefix' => ''],
            'database.connections.cp_pgsql' => ['driver' => 'pgsql', 'database' => 'unused', 'prefix' => ''],
        ]);

        $mysql = Content::on('cp_mysql')->whereCategory('fees-and-costs');
        $this->assertStringContainsString('json_contains(`categories`, ?)', $mysql->toSql());
        $this->assertSame(['[{"slug":"fees-and-costs"}]', '"fees-and-costs"'], $mysql->getBindings());

        $pgsql = Content::on('cp_pgsql')->whereCategory('fees-and-costs')->toSql();
        $this->assertStringContainsString('("categories")::jsonb @> ?', $pgsql);
        $this->assertStringNotContainsString('JSON_SEARCH', $pgsql);
        $this->assertStringNotContainsString('`', $pgsql);
    }
}
