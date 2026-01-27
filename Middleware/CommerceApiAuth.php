<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate Commerce Provisioning API requests using Bearer token.
 *
 * The token is compared against the configured Commerce API secret.
 * Used for internal service provisioning and entitlement management endpoints.
 */
class CommerceApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('API token required. Use Authorization: Bearer <token>');
        }

        $expectedToken = config('services.commerce.api_secret');

        if (! $expectedToken) {
            return response()->json([
                'error' => 'configuration_error',
                'message' => 'Commerce API not configured',
            ], 500);
        }

        if (! hash_equals($expectedToken, $token)) {
            return $this->unauthorized('Invalid API token');
        }

        $request->attributes->set('auth_type', 'commerce_api');

        return $next($request);
    }

    /**
     * Return 401 Unauthorized response.
     */
    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => 'unauthorized',
            'message' => $message,
        ], 401);
    }
}
