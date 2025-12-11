<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RedisLockService
{
    private const SEAT_LOCK_PREFIX = 'lock:seat:';

    /**
     * Acquire 1 lock
     */
    public function acquireLock(string $lockKey, string $lockValue, int $ttlSeconds): bool
    {
        try {
            // setnx + expire
            $result = Redis::set($lockKey, $lockValue, 'NX', 'EX', $ttlSeconds);
            if ($result === true || $result === 'OK') {
                Log::debug("Lock acquired successfully: {$lockKey}");
                return true;
            }

            Log::debug("Lock acquisition failed (already exists): {$lockKey}");
            return false;
        } catch (\Throwable $e) {
            Log::error("Error acquiring lock: {$lockKey}", ['exception' => $e]);
            return false;
        }
    }

    /**
     * Release lock (only if owned)
     */
    public function releaseLock(string $lockKey, string $lockValue): bool
    {
        try {
            $currentValue = Redis::get($lockKey);
            if ($currentValue !== null && $currentValue === $lockValue) {
                $deleted = Redis::del($lockKey);
                if ($deleted > 0) {
                    Log::debug("Lock released successfully: {$lockKey}");
                    return true;
                }
            }

            Log::debug("Lock release failed (not owned or expired): {$lockKey}");
            return false;
        } catch (\Throwable $e) {
            Log::error("Error releasing lock: {$lockKey}", ['exception' => $e]);
            return false;
        }
    }

    public function isLocked(string $lockKey): bool
    {
        try {
            return Redis::exists($lockKey) > 0;
        } catch (\Throwable $e) {
            Log::error("Error checking lock: {$lockKey}", ['exception' => $e]);
            return false;
        }
    }

    public function getLockTtl(string $lockKey): int
    {
        try {
            $ttl = Redis::ttl($lockKey); // giây
            return $ttl ?? -1;
        } catch (\Throwable $e) {
            Log::error("Error getting lock TTL: {$lockKey}", ['exception' => $e]);
            return -1;
        }
    }

    public function extendLock(string $lockKey, string $lockValue, int $additionalSeconds): bool
    {
        try {
            $currentValue = Redis::get($lockKey);
            if ($currentValue === null || $currentValue !== $lockValue) {
                return false;
            }

            $currentTtl = $this->getLockTtl($lockKey);
            if ($currentTtl > 0) {
                return Redis::expire($lockKey, $currentTtl + $additionalSeconds);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("Error extending lock: {$lockKey}", ['exception' => $e]);
            return false;
        }
    }

    public function generateSeatLockKey(string $showtimeId, string $seatId): string
    {
        return self::SEAT_LOCK_PREFIX . $showtimeId . ':' . $seatId;
    }

    /**
     * Lock nhiều ghế atomically
     */
    public function acquireMultipleSeatsLock(string $showtimeId, iterable $seatIds, string $lockToken, int $ttlSeconds): bool
    {
        // check trước
        foreach ($seatIds as $seatId) {
            $seatKey = $this->generateSeatLockKey($showtimeId, $seatId);
            if ($this->isLocked($seatKey)) {
                Log::warning("Seat already locked: {$seatId} for showtime: {$showtimeId}");
                return false;
            }
        }

        // acquire tất cả
        $allLocked = true;
        foreach ($seatIds as $seatId) {
            $seatKey = $this->generateSeatLockKey($showtimeId, $seatId);
            if (!$this->acquireLock($seatKey, $lockToken, $ttlSeconds)) {
                $this->rollbackSeatLocks($showtimeId, $seatIds, $lockToken);
                $allLocked = false;
                break;
            }
        }

        return $allLocked;
    }

    public function releaseMultipleSeatsLock(string $showtimeId, iterable $seatIds, string $lockToken): void
    {
        foreach ($seatIds as $seatId) {
            $seatKey = $this->generateSeatLockKey($showtimeId, $seatId);
            $this->releaseLock($seatKey, $lockToken);
        }
    }

    private function rollbackSeatLocks(string $showtimeId, iterable $seatIds, string $lockToken): void
    {
        Log::warning("Rolling back seat locks for showtime: {$showtimeId}");
        $this->releaseMultipleSeatsLock($showtimeId, $seatIds, $lockToken);
    }

    /**
     * Sinh lockToken ngẫu nhiên
     */
    public function generateLockToken(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Lấy dữ liệu lock từ lockId
     */
    public function getLockData(string $lockId): ?array
    {
        try {
            $key = 'lock:data:' . $lockId;
            $data = Redis::get($key);
            if ($data) {
                return json_decode($data, true);
            }
            return null;
        } catch (\Throwable $e) {
            Log::error("Error getting lock data: {$lockId}", ['exception' => $e]);
            return null;
        }
    }

    /**
     * Lưu dữ liệu lock
     */
    public function storeLockData(string $lockId, array $data, int $ttlSeconds): bool
    {
        try {
            $key = 'lock:data:' . $lockId;
            return Redis::setex($key, $ttlSeconds, json_encode($data));
        } catch (\Throwable $e) {
            Log::error("Error storing lock data: {$lockId}", ['exception' => $e]);
            return false;
        }
    }

    /**
     * Lấy availability cho showtime
     */
    public function getAvailabilityForShowtime(string $showtimeId): array
    {
        // Trả về danh sách ghế available, locked, booked
        // TODO: Implement logic thực tế
        return [
            'showtimeId' => $showtimeId,
            'availableSeats' => [],
            'lockedSeats' => [],
            'bookedSeats' => [],
            'message' => 'Feature under development'
        ];
    }

    /**
     * Giải phóng tất cả lock cho showtime
     */
    public function releaseAllLocksForShowtime(string $showtimeId): void
    {
        try {
            $pattern = self::SEAT_LOCK_PREFIX . $showtimeId . ':*';
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del(...$keys);
                Log::info("Released all locks for showtime: {$showtimeId}");
            }
        } catch (\Throwable $e) {
            Log::error("Error releasing all locks for showtime: {$showtimeId}", ['exception' => $e]);
        }
    }
}
