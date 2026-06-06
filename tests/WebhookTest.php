<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Tests;

use ContentPulse\Core\DTO\ContentItem;
use ContentPulse\Http\ContentPulseClient;
use ContentPulse\Laravel\Models\Content;
use Illuminate\Testing\TestResponse;
use Mockery;

class WebhookTest extends TestCase
{
    private const SECRET = 'shhh-secret';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature ??= hash_hmac('sha256', $body, self::SECRET);

        return $this->call(
            'POST',
            route('contentpulse.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $body,
        );
    }

    public function test_rejects_invalid_signature(): void
    {
        $response = $this->postWebhook(
            ['event' => 'content.deleted', 'data' => ['content_id' => '01HX']],
            signature: 'deadbeef',
        );

        $response->assertUnauthorized();
    }

    public function test_aborts_when_secret_not_configured(): void
    {
        config()->set('contentpulse.webhook_secret', '');

        $response = $this->postWebhook(
            ['event' => 'content.deleted', 'data' => ['content_id' => '01HX']],
            signature: 'anything',
        );

        $response->assertServiceUnavailable();
    }

    public function test_ignores_payload_without_content_id(): void
    {
        $response = $this->postWebhook(['event' => 'content.published', 'data' => []]);

        $response->assertOk();
        $response->assertJson(['status' => 'ignored']);
    }

    public function test_deleted_event_removes_local_copy(): void
    {
        Content::query()->create([
            'external_id' => '01HXTODELETE',
            'slug' => 'to-delete',
            'title' => 'To Delete',
            'status' => 'published',
        ]);

        $response = $this->postWebhook([
            'event' => 'content.deleted',
            'data' => ['content_id' => '01HXTODELETE'],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'event' => 'content.deleted']);
        $this->assertDatabaseMissing('contentpulse_contents', ['external_id' => '01HXTODELETE']);
    }

    public function test_created_event_fetches_and_upserts_item(): void
    {
        $item = ContentItem::fromApiResponse([
            'id' => '01HXCREATED',
            'slug' => 'fresh-article',
            'title' => 'Fresh Article',
            'excerpt' => 'Hot off the press.',
            'status' => 'published',
            'content_type' => 'article',
            'locale' => 'en',
            'faq' => [
                ['question' => 'Is it fresh?', 'answer' => 'Hot off the press.'],
            ],
            'rendered_html' => '<h1>Fresh Article</h1>',
        ]);

        $client = Mockery::mock(ContentPulseClient::class);
        $client->shouldReceive('getContentById')
            ->once()
            ->with('01HXCREATED')
            ->andReturn($item);

        $this->app->instance(ContentPulseClient::class, $client);

        $response = $this->postWebhook([
            'event' => 'content.created',
            'data' => ['content_id' => '01HXCREATED'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contentpulse_contents', [
            'external_id' => '01HXCREATED',
            'slug' => 'fresh-article',
            'title' => 'Fresh Article',
            'rendered_html' => '<h1>Fresh Article</h1>',
        ]);

        $stored = Content::query()->where('external_id', '01HXCREATED')->firstOrFail();
        $this->assertSame(
            [['question' => 'Is it fresh?', 'answer' => 'Hot off the press.']],
            $stored->faq,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
