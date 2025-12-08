<?php
// app/Http/Middleware/JwtAuthMiddleware.php
namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JwtAuthMiddleware
{
    protected TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'code'    => 401,
                'message' => 'Missing or invalid Authorization header',
                'data'    => null,
            ], 401);
        }

        $token = substr($authHeader, 7);

        $user = $this->tokenService->getUserFromAccessToken($token);

        if (!$user) {
            return response()->json([
                'code'    => 401,
                'message' => 'Invalid or expired access token',
                'data'    => null,
            ], 401);
        }

        // Gắn user vào Auth + request
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
