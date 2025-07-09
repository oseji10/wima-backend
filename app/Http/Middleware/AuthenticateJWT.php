<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Support\Facades\Log;

class AuthenticateJWT
{
    protected $jwt;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }

    public function handle(Request $request, Closure $next)
    {
        // Validate the API key from the custom header
        $apiKey = $request->header('X-Childhood-Portal-Key');
        $expectedApiKey = env('CHILDHOOD_PORTAL_API_KEY');
        if (!$apiKey || $apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'Unauthorized: Invalid or missing API key'], 403);
        }

        try {
            // Parse and authenticate the JWT token
            $user = $this->jwt->parseToken()->authenticate();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Get the token payload and check the role
            $payload = $this->jwt->getPayload();
            if ($payload['role'] !== 'pharmacist') {
                return response()->json(['error' => 'Unauthorized: Only pharmacists can perform this action'], 403);
            }

            // Log successful authentication (optional)
            Log::info('JWT authenticated', ['user_id' => $user->id, 'role' => $payload['role']]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Token absent or malformed'], 401);
        }

        return $next($request);
    }
}