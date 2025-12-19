<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class TokenService
{
    protected string $secret;
    protected int $ttl;      // minutes
    protected string $issuer;

    public function __construct()
    {
        // Ưu tiên config('jwt.secret'), nếu chưa set thì fallback JWT_SECRET, rồi tới APP_KEY
        $this->secret = config('jwt.secret', env('JWT_SECRET', config('app.key')));

        $this->ttl    = (int) config('jwt.ttl', 30); // access token sống 30 phút (tùy chỉnh)
        $this->issuer = config('jwt.issuer', 'movie-booking-backend-laravel');
    }

    /** Tạo access token (JWT) */
    public function generateAccessToken(User $user): string
    {
        $now   = time();
        $exp   = $now + $this->ttl * 60;

        $payload = [
            'iss' => $this->issuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'sub' => $user->user_id,
            'role' => $user->role,
            'roles' => [$this->formatRole($user->role)],
            'email' => $user->email,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /** Tạo access token với TTL & roles tùy chỉnh (OAuth flow) */
    public function generateAccessTokenWithClaims(User $user, int $ttlMinutes, array $roles): string
    {
        $now = time();
        $exp = $now + $ttlMinutes * 60;

        $payload = [
            'iss' => $this->issuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'sub' => $user->user_id,
            'role' => $user->role,
            'roles' => $roles,
            'email' => $user->email,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /** Tạo & lưu refresh token cho user */
    public function generateRefreshToken(User $user): RefreshToken
    {
        $tokenString = (string) Str::uuid();

        return RefreshToken::create([
            'user_id'    => $user->user_id,
            'token'      => $tokenString,
            'created_at' => now(),
        ]);
    }

    /** Giải mã access token -> user_id (hoặc ném exception nếu sai) */
    public function decodeAccessToken(string $jwt): array
    {
        $decoded = JWT::decode($jwt, new Key($this->secret, 'HS256'));

        // convert object -> array đơn giản
        return (array) $decoded;
    }

    /** Tìm user theo refresh token hợp lệ */
    public function getUserFromRefreshToken(string $token): ?User
    {
        $record = RefreshToken::where('token', $token)
            ->whereNull('revoked_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $record->user;
    }

    /** Revoke 1 refresh token cụ thể */
    public function revokeRefreshToken(string $token): void
    {
        RefreshToken::where('token', $token)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Revoke tất cả refresh token của user (vd khi đổi mật khẩu) */
    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->user_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllTokensForUser(string $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->update(['revoked_at' => now()]);
    }
    public function getUserFromAccessToken(?string $token): ?User
    {
        if (!$token) {
            return null;
        }

        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->secret, 'HS256')
            );
        } catch (\Throwable $e) {
            return null;
        }

        $userId = $decoded->sub ?? null;

        if (!$userId) {
            return null;
        }

        return User::find($userId);
    }

    protected function formatRole(?string $role): string
    {
        $roleValue = $role ?: 'USER';
        return 'ROLE_' . strtoupper($roleValue);
    }
}
