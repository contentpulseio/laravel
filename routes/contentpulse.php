<?php

declare(strict_types=1);

use ContentPulse\Laravel\Http\Controllers\ResourceController;
use ContentPulse\Laravel\Http\Controllers\WebhookController;
use ContentPulse\Laravel\Http\Middleware\VerifyContentPulseSignature;
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
