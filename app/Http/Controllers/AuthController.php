<?php
// app/Http/Controllers/AuthController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MembershipTier;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // ========== REGISTER ==========

    public function register(Request $request)
{
    $data = $request->validate([
        'username'        => 'required|string|max:255',
        'email'           => 'required|email|unique:users,email',
        'phoneNumber'     => 'nullable|string|max:30',
        'password'        => 'required|string|min:6',
        'confirmPassword' => 'required|string|same:password',
    ]);

    $defaultTier = MembershipTier::where('is_active', true)
        ->orderBy('min_points')
        ->first();

    $user = User::create([
        'username'            => $data['username'],
        'email'               => $data['email'],
        'phoneNumber'         => $data['phoneNumber'] ?? null,
        'password'            => Hash::make($data['password']),
        'provider'            => 'LOCAL',
        'role'                => 'USER',
        'avatar_url'          => null,
        'avatar_cloudinary_id' => null,
        'loyalty_points'      => 0,
        'membership_tier_id'  => $defaultTier?->tier_id,
    ]);

    $accessToken  = $this->tokenService->generateAccessToken($user);
    $refreshModel = $this->tokenService->generateRefreshToken($user);

    // Spec của bạn trả code = 200, mình thống nhất luôn cho giống
    return $this->respond([
        'user'         => $user,
        'accessToken'  => $accessToken,
        'refreshToken' => $refreshModel->token,
    ], 'OK', 200);
}

    // ========== LOGIN ==========

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password'],
            ]);
        }

        $accessToken  = $this->tokenService->generateAccessToken($user);
        $refreshModel = $this->tokenService->generateRefreshToken($user);

        return $this->respond([
            'user'          => $user,
            'accessToken'   => $accessToken,
            'refreshToken'  => $refreshModel->token,
        ], 'Login success');
    }

    // ========== REFRESH ==========

public function refresh(Request $request)
{
    // Ưu tiên giống BE Spring Boot: đọc từ cookie "refresh_token"
    $token = $request->cookie('refresh_token');

    // Cho phép gửi trong body JSON / query để test Postman
    if (!$token) {
        $token = $request->input('refreshToken');
    }

    if (!$token) {
        return $this->respond(null, 'Missing refresh token', 400);
    }

    $user = $this->tokenService->getUserFromRefreshToken($token);

    if (!$user) {
        return $this->respond(null, 'Invalid refresh token', 401);
    }

    // Rotate: revoke token cũ và tạo token mới
    $this->tokenService->revokeRefreshToken($token);
    $newAccess  = $this->tokenService->generateAccessToken($user);
    $newRefresh = $this->tokenService->generateRefreshToken($user);

    // Gắn lại cookie refresh_token (giống Spring Boot dùng cookie)
    $cookie = cookie(
        'refresh_token',
        $newRefresh->token,
        60 * 24 * 30,   // 30 ngày (phút)
        '/',
        null,
        false,          // local: false; lên production nhớ bật true + HTTPS
        true,           // httpOnly
        false,
        'lax'
    );

    return $this->respond([
        'accessToken'  => $newAccess,
        'refreshToken' => $newRefresh->token,
    ], 'OK', 200)->withCookie($cookie);
}

    // ========== LOGOUT ==========

    public function logout(Request $request)
{
    $token = $request->cookie('refreshToken') 
        ?? $request->input('refreshToken'); // cho phép body nếu muốn

    if ($token) {
        $this->tokenService->revokeRefreshToken($token);
    }

    return $this->respond(null, 'OK', 200);
}

     // ========== LOGOUT ALL (mọi session của 1 email) ==========

    public function logoutAll(Request $request)
    {
        // Email có thể gửi qua query (?email=...) hoặc body để test cho dễ
        $email = $request->query('email') ?? $request->input('email');

        if (!$email) {
            return $this->respond(null, 'Email is required', 400);
        }

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->respond(null, 'User not found', 404);
        }

        // Revoke toàn bộ refresh token của user đó
        $this->tokenService->revokeAllTokensForUser($user);

        // Xoá cookie refresh_token hiện tại (nếu có)
        $forgetCookie = cookie()->forget('refresh_token');

        // Trả đúng format spec: data là 1 object rỗng {}
        return $this->respond((object) [], 'OK', 200)->withCookie($forgetCookie);
    }


}
