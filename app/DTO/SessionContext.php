<?php

namespace App\DTO;

use App\Enums\LockOwnerType;

class SessionContext
{
    private string $lockOwnerId;
    private LockOwnerType $lockOwnerType;
    private ?string $userId;

    public function __construct(string $lockOwnerId, LockOwnerType $lockOwnerType, ?string $userId)
    {
        $this->lockOwnerId = $lockOwnerId;
        $this->lockOwnerType = $lockOwnerType;
        $this->userId = $userId;
    }

    public function getLockOwnerId(): string
    {
        return $this->lockOwnerId;
    }

    public function getLockOwnerType(): LockOwnerType
    {
        return $this->lockOwnerType;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function isAuthenticated(): bool
    {
        return $this->lockOwnerType === LockOwnerType::USER && $this->userId !== null;
    }

    public function isGuest(): bool
    {
        return $this->lockOwnerType === LockOwnerType::GUEST;
    }

    public function isUser(): bool
    {
        return $this->lockOwnerType === LockOwnerType::USER;
    }

    public static function forUser(string $userId): self
    {
        return new self($userId, LockOwnerType::USER, $userId);
    }

    public static function forGuest(string $sessionId): self
    {
        return new self($sessionId, LockOwnerType::GUEST, null);
    }
}
