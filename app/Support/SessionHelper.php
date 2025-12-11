<?php

namespace App\Support;

use App\DTO\SessionContext;
use App\Exceptions\CustomException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionHelper
{
    private const SESSION_HEADER = 'X-Session-Id';

    /**
     * Lấy SessionContext:
     * - Nếu đăng nhập: USER (lockOwnerId = user_id)
     * - Nếu guest: lấy từ header X-Session-Id
     */
    public function extractSessionContext(Request $request): SessionContext
    {
        // 1. Ưu tiên user đã auth
        $user = Auth::user();
        if ($user) {
            // 🔥 Dùng user_id (PK của bạn), fallback sang id nếu sau này đổi lại
            $userId = (string) ($user->user_id ?? $user->id ?? '');

            if ($userId === '') {
                throw new CustomException(
                    'Current user id is missing',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            return SessionContext::forUser($userId);
        }

        // 2. Guest – đọc từ header X-Session-Id
        $sessionId = $request->header(self::SESSION_HEADER);
        if ($sessionId && trim($sessionId) !== '') {
            return SessionContext::forGuest(trim($sessionId));
        }

        // 3. Không có gì hết → báo lỗi
        throw new CustomException(
            'Session identifier required. Please provide either authentication token or X-Session-Id header.',
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Bản optional: không bắt buộc phải có, trả về null nếu không có gì.
     */
    public function extractSessionContextOptional(Request $request): ?SessionContext
    {
        try {
            return $this->extractSessionContext($request);
        } catch (CustomException $e) {
            return null;
        }
    }
}
