<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAiImportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByPlainToken($request->bearerToken());

        if (! $token) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated — pass a valid API token as a Bearer token.',
            ], 401);
        }

        $token->update(['last_used_at' => now()]);
        $request->attributes->set('ai_api_token', $token);

        return $next($request);
    }
}
