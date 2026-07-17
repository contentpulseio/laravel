<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Http\Controllers;

use ContentPulse\Core\Exceptions\ContentPulseException;
use ContentPulse\Laravel\Services\ContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function __invoke(Request $request, ContentSyncService $sync): JsonResponse
    {
        $event = (string) ($request->input('event') ?? $request->header('X-Webhook-Event', ''));
        $data = (array) $request->input('data', []);
        $contentId = isset($data['content_id']) ? (string) $data['content_id'] : '';

        if ($contentId === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'missing content_id']);
        }

        try {
            match ($event) {
                'content.created', 'content.updated', 'content.published',
                'content.translation.published', 'content.translation.unpublished' => $sync->syncById($contentId),
                'content.deleted' => $sync->deleteByExternalId($contentId),
                default => null,
            };
        } catch (ContentPulseException $e) {
            Log::warning('ContentPulse webhook sync failed', [
                'event' => $event,
                'content_id' => $contentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'reason' => 'sync_failed'], 502);
        }

        return response()->json(['status' => 'ok', 'event' => $event]);
    }
}
