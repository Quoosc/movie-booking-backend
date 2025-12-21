<?php
// app/Http/Controllers/UsersController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UsersController extends Controller
{
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

        return $this->respond(
            'Password updated successfully'
        );
    }

    // GET /users/loyalty
    public function getLoyalty(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond(
            $this->mapUserToProfile($user)
        );
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
