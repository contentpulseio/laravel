<?php

declare(strict_types=1);

namespace ContentPulse\Laravel;

use ContentPulse\Core\Contracts\ContentClientInterface;
use ContentPulse\Http\ContentPulseClient;
use ContentPulse\Laravel\Commands\SyncCommand;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ContentPulseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('contentpulse')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_contentpulse_contents_table')
            ->hasMigration('add_author_columns_to_contentpulse_contents_table')
            ->hasCommand(SyncCommand::class)
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToStarRepoOnGitHub('contentpulseio/laravel');
            });
    }

    public function packageBooted(): void
    {
        if ((bool) config('contentpulse.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/contentpulse.php');
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ContentPulseClient::class, function (Application $app): ContentPulseClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']['contentpulse'] ?? [];

            return new ContentPulseClient(
                apiKey: (string) ($config['api_key'] ?? ''),
                baseUrl: $config['base_url'] ?? null,
                timeout: (int) ($config['timeout'] ?? 30),
                logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            );
        });

        $this->app->bind(ContentClientInterface::class, ContentPulseClient::class);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ContentPulseClient::class,
            ContentClientInterface::class,
        ];
    }
}
