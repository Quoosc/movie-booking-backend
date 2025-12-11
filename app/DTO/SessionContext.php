<?php

namespace App\DTO;

class SessionContext
{
    public const TYPE_USER          = 'USER';
    public const TYPE_GUEST_SESSION = 'GUEST_SESSION';

    public function __construct(
        public ?string $userId,
        public ?string $guestSessionId,
        public string  $lockOwnerId,
        public string  $type,
    ) {
    }

    public static function forUser(string $userId): self
    {
        return new self(
            userId: $userId,
            guestSessionId: null,
            lockOwnerId: $userId,
            type: self::TYPE_USER,
        );
    }

    public static function forGuest(string $sessionId): self
    {
        return new self(
            userId: null,
            guestSessionId: $sessionId,
            lockOwnerId: $sessionId,
            type: self::TYPE_GUEST_SESSION,
        );
    }

    public function isUser(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function isGuest(): bool
    {
        return $this->type === self::TYPE_GUEST_SESSION;
    }
}
