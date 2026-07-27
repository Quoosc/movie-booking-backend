<?php

namespace App\Modules\User\Controllers;

use App\Core\Http\Controllers\BaseController;

use App\Models\User;
use App\Shared\Services\TokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends BaseController
{
    public function __construct(
        protected TokenService $tokenService
    ) {}

    /**
     * GET /oauth2/authorization/google
     * Start Google OAuth2 login (Spring-compatible endpoint).
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * GET /login/oauth2/code/google
     * Handle Google OAuth2 callback (Spring-compatible endpoint).
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('[oauth.google] callback failed', ['error' => $e->getMessage()]);
            return redirect($this->frontendRedirectUrl() . '?error=oauth_failed');
        }

        $email = $googleUser->getEmail();
        $name = $googleUser->getName();

        if (!$email) {
            Log::error('[oauth.google] missing email in profile');
            return redirect($this->frontendRedirectUrl() . '?error=missing_email');
        }

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'username' => $name ?: $email,
                'email' => $email,
                'password' => null,
                'provider' => 'google',
                'role' => 'USER',
            ]);
        } elseif (strtoupper((string) $user->role) === 'GUEST') {
            $user->role = 'USER';
            $user->provider = 'google';
            $user->save();
        }

        $accessToken = $this->tokenService->generateAccessTokenWithClaims(
            $user,
            60,
            ['ROLE_'.strtoupper((string) $user->role)]
        );
        $refreshModel = $this->tokenService->generateRefreshToken($user);

        $secureCookies = (bool) (config('session.secure') ?? app()->environment('production'));
        $sameSite = (string) config('session.same_site', 'lax');

        $accessCookie = cookie(
            'access_token',
            $accessToken,
            60,      // 1 hour
            '/',
            null,
            $secureCookies,
            true,    // httpOnly
            false,
            $sameSite
        );

        $refreshCookie = cookie(
            'refresh_token',
            $refreshModel->token,
            (int) config('jwt.refresh_ttl', 60 * 24 * 30),
            '/',
            null,
            $secureCookies,
            true,
            false,
            $sameSite
        );

        return redirect($this->frontendRedirectUrl())
            ->withCookie($accessCookie)
            ->withCookie($refreshCookie);
    }

    protected function frontendRedirectUrl(): string
    {
        return (string) config('services.google.frontend_redirect', env(
            'FRONTEND_REDIRECT_URL',
            'http://localhost:5173/oauth2/success'
        ));
    }
}
