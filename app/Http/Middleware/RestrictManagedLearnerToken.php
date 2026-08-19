<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictManagedLearnerToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->user()?->currentAccessToken();
        if ($token === null || $token->can('*')) {
            return $next($request);
        }

        $ability = collect($token->abilities ?? [])
            ->first(static fn(string $value): bool => str_starts_with($value, 'managed-workspace:'));
        if ($ability === null) {
            return $next($request);
        }
        $workspaceId = substr($ability, strlen('managed-workspace:'));
        $path = $request->path();
        $method = $request->method();
        $workspace = preg_quote($workspaceId, '#');
        $allowed =
            ($method === 'POST' && $path === 'api/v1/logout')
            || ($method === 'GET' && $path === 'api/v1/push/config')
            || (in_array($method, ['POST', 'DELETE'], true) && $path === 'api/v1/push/subscriptions')
            || ($method === 'GET' && $path === 'api/v1/quizzes')
            || ($method === 'GET' && preg_match("#^api/v1/workspaces/{$workspace}/learning/(today|reviews/due)$#", $path) === 1)
            || ($method === 'POST' && preg_match("#^api/v1/workspaces/{$workspace}/learning/assignments/[^/]+/attempts$#", $path) === 1)
            || ($method === 'POST' && preg_match("#^api/v1/workspaces/{$workspace}/learning/attempts/[^/]+/submit$#", $path) === 1)
            || ($method === 'POST' && preg_match("#^api/v1/workspaces/{$workspace}/learning/reviews/[^/]+/answer$#", $path) === 1)
            || ($method === 'POST' && $path === "api/v1/workspaces/{$workspaceId}/learning/tutor/explain");

        if (!$allowed) {
            return new JsonResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
