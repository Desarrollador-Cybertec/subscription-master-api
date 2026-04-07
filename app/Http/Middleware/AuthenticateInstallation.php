<?php

namespace App\Http\Middleware;

use App\Models\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key required',
            ], 401);
        }

        $hash = hash('sha256', $apiKey);
        $installation = Installation::where('api_key_hash', $hash)->first();

        if (!$installation) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Cross-validate installation_id if provided in body or query
        $requestInstallationId = $request->input('installation_id') ?? $request->query('installation_id');
        if ($requestInstallationId && $requestInstallationId !== $installation->id) {
            return response()->json([
                'error' => 'Installation ID mismatch',
            ], 403);
        }

        // Cross-validate product if provided
        $requestProduct = $request->input('product') ?? $request->query('product');
        if ($requestProduct && $requestProduct !== $installation->product->value) {
            return response()->json([
                'error' => 'Product mismatch',
            ], 403);
        }

        $request->attributes->set('installation', $installation);

        return $next($request);
    }
}
