<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OptionalJwtAuthMiddleware
{
    public function __construct(
        protected TokenService $tokenService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = null;
        $token = $request->bearerToken();

        if ($token) {
            $user = $this->tokenService->getUserFromAccessToken($token);
        }

        if (!$user) {
            $cookieToken = $request->cookie('access_token');
            if ($cookieToken) {
                $user = $this->tokenService->getUserFromAccessToken($cookieToken);
            }
        }

        if ($user) {
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        }

        return $next($request);
    }
}
