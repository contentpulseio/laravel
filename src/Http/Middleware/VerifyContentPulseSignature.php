<?php

declare(strict_types=1);

namespace ContentPulse\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyContentPulseSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('contentpulse.webhook_secret', '');

        if ($secret === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'ContentPulse webhook secret is not configured.');
        }

        $provided = (string) $request->header('X-Webhook-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
