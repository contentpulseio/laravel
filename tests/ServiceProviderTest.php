<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Core\Contracts\ContentClientInterface;
use ContentPulse\Http\ContentPulseClient;
use ContentPulse\Laravel\ContentPulseServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
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
        $app['config']->set('contentpulse.api_key', 'test-key');
        $app['config']->set('contentpulse.base_url', 'https://example.test');
        $app['config']->set('contentpulse.timeout', 15);
    }

    public function test_container_bindings_resolve(): void
    {
        $this->assertInstanceOf(ContentPulseClient::class, $this->app->make(ContentPulseClient::class));
        $this->assertInstanceOf(ContentPulseClient::class, $this->app->make(ContentClientInterface::class));
    }

    public function test_client_is_configured_from_config(): void
    {
        $client = $this->app->make(ContentPulseClient::class);

        $this->assertSame('https://example.test', $client->getBaseUrl());
    }

    public function test_config_is_merged(): void
    {
        $this->assertSame('test-key', config('contentpulse.api_key'));
    }

    public function test_sync_command_is_registered(): void
    {
        $this->assertArrayHasKey('contentpulse:sync', Artisan::all());
    }

    public function test_install_command_is_registered(): void
    {
        $this->assertArrayHasKey('contentpulse:install', Artisan::all());
    }
}
