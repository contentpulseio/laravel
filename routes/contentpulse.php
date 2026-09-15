<?php

declare(strict_types=1);

use ContentPulse\Laravel\Http\Controllers\ResourceController;
use ContentPulse\Laravel\Http\Controllers\WebhookController;
use ContentPulse\Laravel\Http\Middleware\VerifyContentPulseSignature;
use ContentPulse\Laravel\Support\Locale;
use Illuminate\Support\Facades\Route;

/** @var array<string, mixed> $config */
$config = (array) config('contentpulse.routes', []);

Route::post(
    (string) ($config['webhook_path'] ?? 'webhooks/contentpulse'),
    WebhookController::class,
)
    ->middleware(VerifyContentPulseSignature::class)
    ->name('contentpulse.webhook');

Route::middleware((array) ($config['middleware'] ?? ['web']))
    ->prefix((string) ($config['prefix'] ?? 'resources'))
    ->group(function (): void {
        Route::get('/', [ResourceController::class, 'index'])->name('contentpulse.index');
        Route::get('/{slug}', [ResourceController::class, 'show'])->name('contentpulse.show');
    });

// Locale-first URLs are opt-in through localization.route_mode. The legacy
// unprefixed routes above remain available for backwards compatibility.
Route::middleware((array) ($config['middleware'] ?? ['web']))
    ->prefix('{locale}/'.(string) ($config['prefix'] ?? 'resources'))
    ->where(['locale' => Locale::routePattern()])
    ->group(function (): void {
        Route::get('/', [ResourceController::class, 'index'])->name('contentpulse.locale.index');
        Route::get('/{slug}', [ResourceController::class, 'show'])->name('contentpulse.locale.show');
    });
