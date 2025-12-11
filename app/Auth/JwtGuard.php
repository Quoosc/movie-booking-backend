<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class JwtGuard implements Guard
{
    protected $user;
    protected $provider;
    protected $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    public function check()
    {
        return !is_null($this->user());
    }

    public function guest()
    {
        return !$this->check();
    }

    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        try {
            $secret = config('jwt.secret');
            $algorithm = config('jwt.algorithm', 'HS256');

            $decoded = JWT::decode($token, new Key($secret, $algorithm));

            $userId = $decoded->sub ?? null;

            if ($userId) {
                $this->user = $this->provider->retrieveById($userId);
            }

            return $this->user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function id()
    {
        if ($user = $this->user()) {
            return $user->getAuthIdentifier();
        }

        return null;
    }

    public function validate(array $credentials = [])
    {
        return false;
    }

    public function hasUser()
    {
        return !is_null($this->user);
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    protected function getTokenFromRequest()
    {
        $header = $this->request->header('Authorization', '');

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
