<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        // Log the request origin for debugging
        $origin = $request->header('Origin', 'none');
        Log::info('CORS: Request Origin: ' . $origin . ' | URL: ' . $request->url());

        // Define allowed origins
        $allowedOrigins = [
            'https://portal.sokotopalliativeshop.com.ng',
            'http://localhost:3000',
            // Add other origins if needed, e.g., 'http://localhost:3001'
        ];

        // Handle OPTIONS preflight requests
        $response = $request->getMethod() === 'OPTIONS'
            ? response('', 200)
            : $next($request);

        // Set CORS headers if origin is allowed
        if (in_array($origin, $allowedOrigins) || $origin === 'none') {
            $response->header('Access-Control-Allow-Origin', $origin === 'none' ? '*' : $origin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
            $response->header('Access-Control-Max-Age', '1728000');
            $response->header('Access-Control-Allow-Credentials', 'true'); // Required for cookies
        } else {
            Log::warning('CORS: Origin not allowed: ' . $origin);
        }

        return $response;
    }
}