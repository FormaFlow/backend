<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddApiVersionHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/v1/*')) {
            $response->headers->set('API-Version', 'v1');
        } elseif ($request->is('api/v2/*')) {
            $response->headers->set('API-Version', 'v2');
        }

        return $response;
    }
}
