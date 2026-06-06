<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Laravel\ContentPulseServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ContentPulseServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        // Required for the `web` middleware group (sessions/encryption) the
        // public content routes run through.
        $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $config->set('contentpulse.api_key', 'test-key');
        $config->set('contentpulse.base_url', 'https://example.test');
        $config->set('contentpulse.timeout', 15);
        $config->set('contentpulse.webhook_secret', 'shhh-secret');
        $config->set('contentpulse.layout', 'layouts.app');
        $config->set('contentpulse.table', 'contentpulse_contents');

        // Host app owns the layout chrome; register the fixture layout.
        $config->set('view.paths', array_merge(
            [__DIR__.'/fixtures/views'],
            (array) $config->get('view.paths', []),
        ));
    }

    protected function defineDatabaseMigrations(): void
    {
        // Run the shipped migration stub directly so tests validate the real
        // schema hosts get when they publish migrations.
        $migration = require __DIR__.'/../database/migrations/create_contentpulse_contents_table.php.stub';
        $migration->up();
    }
}
