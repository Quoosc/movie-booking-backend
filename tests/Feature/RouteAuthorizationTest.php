<?php

namespace Tests\Feature;

use App\Models\User;
use App\Shared\Services\TokenService;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    #[DataProvider('adminRouteProvider')]
    public function test_admin_routes_require_authentication_and_admin_role(
        string $method,
        string $uri
    ): void {
        $route = $this->findRoute($method, $uri);

        $this->assertNotNull($route, "Route {$method} {$uri} is not registered");
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth.jwt', $middleware);
        $this->assertContains('role:admin', $middleware);
    }

    public static function adminRouteProvider(): array
    {
        return [
            ['POST', 'api/movies'],
            ['POST', 'api/seats'],
            ['POST', 'api/promotions'],
            ['GET', 'api/users'],
            ['PATCH', 'api/users/{userId}/role'],
            ['POST', 'api/membership-tiers'],
            ['POST', 'api/showtimes'],
            ['PUT', 'api/showtime-seats/{id}'],
            ['POST', 'api/price-base'],
            ['POST', 'api/price-modifiers'],
            ['POST', 'api/ticket-types'],
            ['POST', 'api/showtimes/{showtimeId}/ticket-types'],
            ['POST', 'api/cinemas'],
            ['POST', 'api/cinemas/rooms'],
            ['POST', 'api/hero-slides'],
            ['POST', 'api/payments/{paymentId}/refund'],
            ['GET', 'api/movies/reviews/moderation'],
            ['PATCH', 'api/movies/reviews/{reviewId}/moderate'],
        ];
    }

    public function test_logout_all_requires_a_valid_access_token(): void
    {
        $route = $this->findRoute('POST', 'api/auth/logout-all');

        $this->assertNotNull($route);
        $this->assertContains('auth.jwt', $route->gatherMiddleware());
    }

    public function test_profile_routes_remain_available_to_regular_users(): void
    {
        $route = $this->findRoute('GET', 'api/users/profile');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth.jwt', $middleware);
        $this->assertNotContains('role:admin', $middleware);
    }

    #[DataProvider('authenticatedUserRouteProvider')]
    public function test_new_account_routes_require_authentication_without_admin_role(
        string $method,
        string $uri
    ): void {
        $route = $this->findRoute($method, $uri);

        $this->assertNotNull($route, "Route {$method} {$uri} is not registered");
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth.jwt', $middleware);
        $this->assertNotContains('role:admin', $middleware);
    }

    public static function authenticatedUserRouteProvider(): array
    {
        return [
            ['GET', 'api/users/loyalty/transactions'],
            ['GET', 'api/notifications'],
            ['GET', 'api/notifications/unread-count'],
            ['PATCH', 'api/notifications/read-all'],
            ['PATCH', 'api/notifications/{notificationId}/read'],
        ];
    }

    public function test_regular_user_is_actually_rejected_by_admin_middleware(): void
    {
        $user = new User();
        $user->user_id = 'user-1';
        $user->role = 'USER';

        $tokens = $this->mock(TokenService::class);
        $tokens->shouldReceive('getUserFromAccessToken')
            ->once()
            ->with('valid-user-token')
            ->andReturn($user);

        $this->withToken('valid-user-token')
            ->postJson('/api/movies', [])
            ->assertForbidden();
    }

    private function findRoute(string $method, string $uri): ?Route
    {
        foreach (app('router')->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }
}
