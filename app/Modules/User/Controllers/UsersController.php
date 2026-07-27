<?php

namespace App\Modules\User\Controllers;

// app/Http/Controllers/UsersController.php

use App\Core\Http\Controllers\BaseController;

use App\Models\User;
use App\Models\LoyaltyTransaction;
use App\Models\MembershipTier;
use App\Shared\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UsersController extends BaseController
{
    public function __construct(protected TokenService $tokenService)
    {
    }

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function mapUserToProfile(User $user): array
    {
        $tier = $user->membershipTier;

        return [
            'userId'      => $user->user_id,
            'email'       => $user->email,
            'username'    => $user->username,
            'phoneNumber' => $user->phoneNumber,
            'role'        => strtoupper($user->role ?? 'USER'),
            'avatarUrl'   => $user->avatar_url,
            'avatarCloudinaryId' => $user->avatar_cloudinary_id,
            'loyaltyPoints'      => $user->loyalty_points,
            'membershipTier' => $tier ? [
                'membershipTierId' => $tier->membership_tier_id,
                'name'             => $tier->name,
                'minPoints'        => $tier->min_points,
                'discountType'     => $tier->discount_type,
                'discountValue'    => $tier->discount_value,
                'description'      => $tier->description,
                'isActive'         => $tier->is_active,
                'createdAt'        => optional($tier->created_at)->toIso8601String(),
                'updatedAt'        => optional($tier->updated_at)->toIso8601String(),
            ] : null,
            'createdAt' => optional($user->created_at)->toIso8601String(),
            'updatedAt' => optional($user->updated_at)->toIso8601String(),
        ];
    }

    // GET /users/profile
    public function getProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond(
            $this->mapUserToProfile($user)
        );
    }

    // PUT /users/profile
    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'username'    => 'required|string|max:255',
            'phoneNumber' => 'nullable|string|max:30',
            'avatarUrl'   => 'nullable|string|max:500',
        ]);

        $user->username    = $data['username'];
        $user->phoneNumber = $data['phoneNumber'] ?? $user->phoneNumber;
        $user->avatar_url  = $data['avatarUrl']   ?? $user->avatar_url;
        $user->save();

        return $this->respond(
            $this->mapUserToProfile($user)
        );
    }

    // PATCH /users/password
    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'currentPassword' => 'required|string',
            'newPassword'     => 'required|string|min:6',
            'confirmPassword' => 'required|string|min:6',
        ]);

        if ($data['newPassword'] !== $data['confirmPassword']) {
            throw ValidationException::withMessages([
                'confirmPassword' => ['Password confirmation does not match'],
            ]);
        }

        if (!Hash::check($data['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['Current password is incorrect'],
            ]);
        }

        $user->password = Hash::make($data['newPassword']);
        $user->save();
        $this->tokenService->revokeAllTokensForUser($user->user_id);

        return $this->respond(
            'Password updated successfully'
        );
    }

    // GET /users/loyalty
    public function getLoyalty(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $this->mapUserToProfile($user->load('membershipTier'));
        $points = (int) ($user->loyalty_points ?? 0);
        $currentMinimum = (int) ($user->membershipTier?->min_points ?? 0);
        $nextTier = MembershipTier::where('is_active', true)
            ->where('min_points', '>', $points)
            ->orderBy('min_points')
            ->first();

        return $this->respond([
            ...$profile,
            'loyaltyOverview' => [
                'currentPoints' => $points,
                'nextTier' => $nextTier ? [
                    'membershipTierId' => $nextTier->membership_tier_id,
                    'name' => $nextTier->name,
                    'minPoints' => $nextTier->min_points,
                    'pointsNeeded' => max(0, $nextTier->min_points - $points),
                ] : null,
                'progressPercent' => $nextTier
                    ? min(100, round((($points - $currentMinimum) / max(1, $nextTier->min_points - $currentMinimum)) * 100, 1))
                    : 100,
            ],
        ]);
    }

    // GET /users/loyalty/transactions
    public function getLoyaltyTransactions(Request $request)
    {
        $data = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'perPage' => 'sometimes|integer|min:1|max:50',
            'type' => 'sometimes|in:EARN,REVERSAL,REDEEM,ADJUSTMENT,OPENING_BALANCE',
        ]);
        $query = LoyaltyTransaction::where('user_id', $request->user()->user_id);
        if (isset($data['type'])) $query->where('type', $data['type']);

        $summaryQuery = LoyaltyTransaction::where('user_id', $request->user()->user_id);
        $earned = (clone $summaryQuery)->where('points', '>', 0)->sum('points');
        $spent = abs((int) (clone $summaryQuery)->where('points', '<', 0)->sum('points'));
        $paginator = $query->latest()->paginate($data['perPage'] ?? 15);

        return $this->respond([
            'items' => collect($paginator->items())->map(fn (LoyaltyTransaction $transaction) => [
                'transactionId' => $transaction->loyalty_transaction_id,
                'type' => $transaction->type,
                'points' => $transaction->points,
                'balanceAfter' => $transaction->balance_after,
                'sourceType' => $transaction->source_type,
                'sourceId' => $transaction->source_id,
                'description' => $transaction->description,
                'expiresAt' => $transaction->expires_at?->toISOString(),
                'createdAt' => $transaction->created_at?->toISOString(),
            ]),
            'summary' => [
                'totalEarned' => (int) $earned,
                'totalSpent' => $spent,
                'currentBalance' => (int) ($request->user()->loyalty_points ?? 0),
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    // GET /users/{userId} - Admin only
    public function getUserById($userId)
    {
        $user = User::where('user_id', $userId)->first();

        if (!$user) {
            return $this->respond(null, 'User not found', 404);
        }

        return $this->respond($this->mapUserToProfile($user));
    }

    // GET /users - Admin only, list all users
    public function listAllUsers()
    {
        $users = User::all();

        $mappedUsers = $users->map(function ($user) {
            return $this->mapUserToProfile($user);
        })->toArray();

        return $this->respond($mappedUsers);
    }

    // PATCH /users/{userId}/role - Admin only
    public function updateUserRole($userId, Request $request)
    {
        $user = User::where('user_id', $userId)->first();

        if (!$user) {
            return $this->respond(null, 'User not found', 404);
        }

        $data = $request->validate([
            'role' => 'required|string|in:USER,ADMIN',
        ]);

        $user->role = $data['role'];
        $user->save();

        return $this->respond($this->mapUserToProfile($user));
    }

    // DELETE /users/{userId} - Admin only
    public function deleteUser($userId)
    {
        $user = User::where('user_id', $userId)->first();

        if (!$user) {
            return $this->respond(null, 'User not found', 404);
        }

        $user->delete();

        return $this->respond('User deleted successfully');
    }
}
