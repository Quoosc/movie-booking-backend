<?php

namespace Tests\Unit;

use App\Core\Auth\JwtGuard;
use App\Models\User;
use App\Shared\Services\TokenService;
use App\Shared\Support\JwtSigningKey;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class JwtSigningKeyTest extends TestCase
{
    public function test_it_uses_a_strong_configured_jwt_secret_directly(): void
    {
        $secret = str_repeat('j', 32);
        config(['jwt.secret' => $secret]);

        $this->assertSame($secret, JwtSigningKey::resolve());
    }

    public function test_it_derives_a_valid_key_from_app_key_when_jwt_secret_is_short(): void
    {
        config([
            'jwt.secret' => 'legacy-short-secret',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        $first = JwtSigningKey::resolve();
        $second = JwtSigningKey::resolve();

        $this->assertSame(32, strlen($first));
        $this->assertSame($first, $second);
        $this->assertNotSame('legacy-short-secret', $first);
    }

    public function test_token_service_can_encode_and_decode_with_a_legacy_short_secret(): void
    {
        config([
            'jwt.secret' => 'legacy-short-secret',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        $user = new User;
        $user->forceFill([
            'user_id' => 'user-1',
            'email' => 'user@example.com',
            'role' => 'USER',
        ]);

        $service = new TokenService;
        $token = $service->generateAccessToken($user);
        $claims = $service->decodeAccessToken($token);

        $this->assertSame('user-1', $claims['sub']);
        $this->assertSame('user@example.com', $claims['email']);
    }

    public function test_jwt_guard_accepts_a_token_generated_from_a_legacy_short_secret(): void
    {
        config([
            'jwt.secret' => 'legacy-short-secret',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        $user = new User;
        $user->forceFill([
            'user_id' => 'user-1',
            'email' => 'user@example.com',
            'role' => 'USER',
        ]);

        $token = (new TokenService)->generateAccessToken($user);
        $request = Request::create('/api/users/me');
        $request->headers->set('Authorization', 'Bearer '.$token);

        $provider = Mockery::mock(UserProvider::class);
        $provider->shouldReceive('retrieveById')
            ->once()
            ->with('user-1')
            ->andReturn($user);

        $guard = new JwtGuard($provider, $request);

        $this->assertSame($user, $guard->user());
    }

    public function test_it_rejects_configuration_when_all_key_material_is_too_short(): void
    {
        config([
            'jwt.secret' => 'short',
            'app.key' => 'also-short',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT signing key is too short');

        JwtSigningKey::resolve();
    }
}
