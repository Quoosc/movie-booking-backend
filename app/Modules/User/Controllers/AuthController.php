<?php

namespace App\Modules\User\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\MembershipTier;
use App\Models\User;
use App\Shared\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function __construct(protected TokenService $tokenService)
    {
    }

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    private function refreshTokenCookie(string $token)
    {
        return cookie(
            'refresh_token',
            $token,
            (int) config('jwt.refresh_ttl', 60 * 24 * 30),
            '/',
            null,
            (bool) (config('session.secure') ?? app()->environment('production')),
            true,
            false,
            (string) config('session.same_site', 'lax')
        );
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email',
            'phoneNumber' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
            'confirmPassword' => 'required|string|same:password',
        ]);

        $existing = User::where('email', $data['email'])->first();
        if ($existing && strtoupper((string) $existing->role) !== 'GUEST') {
            throw ValidationException::withMessages([
                'email' => ['Email already exists'],
            ]);
        }

        $defaultTier = MembershipTier::where('is_active', true)
            ->orderBy('min_points')
            ->first();

        if ($existing) {
            $existing->username = $data['username'];
            $existing->phoneNumber = $data['phoneNumber'] ?? $existing->phoneNumber;
            $existing->password = Hash::make($data['password']);
            $existing->provider = 'LOCAL';
            $existing->role = 'USER';
            if (!$existing->membership_tier_id) {
                $existing->membership_tier_id = $defaultTier?->membership_tier_id;
            }
            $existing->save();
            $user = $existing;
        } else {
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'phoneNumber' => $data['phoneNumber'] ?? null,
                'password' => Hash::make($data['password']),
                'provider' => 'LOCAL',
                'role' => 'USER',
                'avatar_url' => null,
                'avatar_cloudinary_id' => null,
                'loyalty_points' => 0,
                'membership_tier_id' => $defaultTier?->membership_tier_id,
            ]);
        }

        $accessToken = $this->tokenService->generateAccessToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken($user);

        return $this->respond([
            'user' => $user,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken->token,
        ])->withCookie($this->refreshTokenCookie($refreshToken->token));
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();
        if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password'],
            ]);
        }

        $accessToken = $this->tokenService->generateAccessToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken($user);

        return $this->respond([
            'user' => $user,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken->token,
        ], 'Login success')->withCookie($this->refreshTokenCookie($refreshToken->token));
    }

    public function refresh(Request $request)
    {
        $token = $request->cookie('refresh_token')
            ?? $request->input('refreshToken')
            ?? $request->input('refresh_token');

        if (!$token) {
            return $this->respond(null, 'Missing refresh token', 400);
        }

        $user = $this->tokenService->getUserFromRefreshToken($token);
        if (!$user) {
            return $this->respond(null, 'Invalid or expired refresh token', 401);
        }

        $this->tokenService->revokeRefreshToken($token);
        $newAccessToken = $this->tokenService->generateAccessToken($user);
        $newRefreshToken = $this->tokenService->generateRefreshToken($user);

        return $this->respond([
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken->token,
        ])->withCookie($this->refreshTokenCookie($newRefreshToken->token));
    }

    public function logout(Request $request)
    {
        $token = $request->cookie('refresh_token')
            ?? $request->input('refreshToken')
            ?? $request->input('refresh_token');

        if ($token) {
            $this->tokenService->revokeRefreshToken($token);
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->forgetAuthCookies($this->respond(null));
    }

    public function logoutAll(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) {
            return $this->respond(null, 'Unauthenticated', 401);
        }

        $this->tokenService->revokeAllTokensForUser($user->user_id);

        return $this->forgetAuthCookies($this->respond((object) []));
    }

    private function forgetAuthCookies($response)
    {
        return $response
            ->withCookie(cookie('access_token', '', -1, '/'))
            ->withCookie(cookie('refresh_token', '', -1, '/api/auth'))
            ->withCookie(cookie('refresh_token', '', -1, '/auth'))
            ->withCookie(cookie('refresh_token', '', -1, '/'));
    }
}
