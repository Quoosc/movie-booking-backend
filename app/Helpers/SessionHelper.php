<?php

namespace App\Helpers;

use App\DTO\SessionContext;
use App\Enums\LockOwnerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\CustomException;
use Illuminate\Support\Str;

class SessionHelper
{
    public const SESSION_HEADER = 'X-Session-Id';

    /**
     * Extract session context from request (required)
     * @throws CustomException if neither JWT nor X-Session-Id is present
     */
    public function extractSessionContext(Request $request): SessionContext
    {
        // 1. Check JWT authentication
        $user = Auth::guard('api')->user();

        if ($user) {
            return SessionContext::forUser($user->user_id);
        }

        // 2. Get X-Session-Id header
        $sessionId = $request->header(self::SESSION_HEADER);

        if ($sessionId) {
            // 3. Validate UUID format
            if (!$this->isValidUuid($sessionId)) {
                throw new CustomException('Invalid session ID format. Must be a valid UUID.', 400);
            }

            return SessionContext::forGuest($sessionId);
        }

        // 4. Throw exception if neither
        throw new CustomException(
            'Authentication required: provide JWT token or ' . self::SESSION_HEADER . ' header',
            401
        );
    }

    /**
     * Extract session context from request (optional)
     * Returns null if neither JWT nor X-Session-Id is present
     */
    public function extractSessionContextOptional(Request $request): ?SessionContext
    {
        try {
            return $this->extractSessionContext($request);
        } catch (CustomException $e) {
            return null;
        }
    }

    /**
     * Validate UUID format
     */
    public function isValidUuid(string $uuid): bool
    {
        return Str::isUuid($uuid);
    }
}
