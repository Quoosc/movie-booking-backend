<?php

namespace App\Services;

use App\Models\User;
use App\Models\Booking;
use App\Models\RefreshToken;
use App\Services\MembershipTierService;
use App\Exceptions\EntityDeletionForbiddenException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserService
{
    public function __construct(
        protected MembershipTierService $membershipTierService,
    ) {}

    /**
     * Tìm user theo email, dùng cho Auth / chỗ nào cần.
     */
    public function findByEmail(string $email): User
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        return $user;
    }

    /**
     * Tìm user theo id (UUID).
     */
    public function findUserById(string $id): User
    {
        $user = User::find($id);

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        return $user;
    }

    /**
     * Lấy current user từ Auth.
     */
    public function getCurrentUser(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            throw new NotFoundHttpException('User not authenticated');
        }

        return $user;
    }

    // ================== LOYALTY POINTS ==================

    /**
     * Cộng điểm loyalty cho user.
     * Rule: 1 điểm / 1000 VND.
     */
    public function addLoyaltyPoints(string $userId, float $amountSpent): void
    {
        DB::transaction(function () use ($userId, $amountSpent) {
            $user = $this->findUserById($userId);

            $pointsToAdd = (int) floor($amountSpent / 1000.0);
            $user->loyalty_points = ($user->loyalty_points ?? 0) + $pointsToAdd;

            $this->updateUserTier($user);
            $user->save();
        });
    }

    /**
     * Trừ điểm loyalty (khi refund, hủy vé).
     */
    public function revokeLoyaltyPoints(string $userId, float $amount): void
    {
        DB::transaction(function () use ($userId, $amount) {
            $user = $this->findUserById($userId);

            $pointsToRemove = (int) floor($amount / 1000.0);
            $newPoints = max(0, ($user->loyalty_points ?? 0) - $pointsToRemove);
            $user->loyalty_points = $newPoints;

            $this->updateUserTier($user);
            $user->save();
        });
    }

    /**
     * Cập nhật tier theo tổng điểm hiện tại.
     */
    public function updateUserTier(User $user): void
    {
        // MembershipTierService của mình nên có hàm này:
        // public function getAppropriateTier(int $points): ?MembershipTier
        $tier = $this->membershipTierService->getAppropriateTier($user->loyalty_points ?? 0);

        if ($tier && $user->membership_tier_id !== $tier->membership_tier_id) {
            $user->membership_tier_id = $tier->membership_tier_id;
        }
    }

    // ================== ADMIN HỖ TRỢ (NẾU CÓ DÙNG) ==================

    /**
     * Admin đổi role user.
     * Chấp nhận: ADMIN / USER / GUEST
     */
    public function updateUserRole(string $userId, string $role): User
    {
        $user = $this->findUserById($userId);

        $normalized = strtoupper(trim(str_replace('"', '', $role)));

        if (!in_array($normalized, ['ADMIN', 'USER', 'GUEST'], true)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $user->role = $normalized;
        $user->save();

        return $user;
    }

    /**
     * Admin xóa user (có kiểm tra booking + refresh token).
     */
    public function deleteUser(string $userId): void
    {
        $user = $this->findUserById($userId);

        $bookingCount = Booking::where('user_id', $userId)->count();
        if ($bookingCount > 0) {
            throw new EntityDeletionForbiddenException(
                "Cannot delete user with existing bookings ({$bookingCount})."
            );
        }

        $tokenCount = RefreshToken::where('user_id', $userId)->count();
        if ($tokenCount > 0) {
            throw new EntityDeletionForbiddenException(
                "Cannot delete user with active sessions ({$tokenCount} refresh tokens). " .
                "Please revoke all sessions first."
            );
        }

        $user->delete();
    }
}
