<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConfirmBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'lockId' => 'required|uuid|exists:seat_locks,seat_lock_id',
            'promotionCode' => 'nullable|string|max:50',
            'snackCombos' => 'nullable|array',
            'snackCombos.*.snackId' => 'required|uuid|exists:snacks,snack_id',
            'snackCombos.*.quantity' => 'required|integer|min:1|max:10',
        ];

        // Skip guest info for authenticated users (JWT bearer)
        $isAuthenticated = Auth::guard('api')->check() || (bool) $this->bearerToken();

        if (!$isAuthenticated) {
            $rules = array_merge($rules, [
                'guestInfo' => 'required|array',
                'guestInfo.email' => 'required_with:guestInfo|email|max:255',
                // Accept either fullName or username for guest name to align with client payloads
                'guestInfo.fullName' => 'required_with:guestInfo|required_without:guestInfo.username|string|max:255',
                'guestInfo.username' => 'required_with:guestInfo|required_without:guestInfo.fullName|string|max:255',
                'guestInfo.phoneNumber' => 'required_with:guestInfo|string|max:20',
            ]);
        } else {
            $rules['guestInfo'] = 'nullable|array';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'lockId.required' => 'Lock ID là bắt buộc',
            'lockId.uuid' => 'Lock ID phải là UUID hợp lệ',
            'lockId.exists' => 'Lock không tồn tại hoặc đã hết hạn',
            'guestInfo.email.required_with' => 'Email là bắt buộc cho khách',
            'guestInfo.email.email' => 'Email không hợp lệ',
            'guestInfo.fullName.required_with' => 'Họ tên là bắt buộc cho khách',
            'guestInfo.fullName.required_without' => 'Họ tên hoặc username là bắt buộc cho khách',
            'guestInfo.username.required_with' => 'Username là bắt buộc cho khách nếu thiếu họ tên',
            'guestInfo.username.required_without' => 'Username hoặc họ tên là bắt buộc cho khách',
            'guestInfo.phoneNumber.required_with' => 'Số điện thoại là bắt buộc cho khách',
        ];
    }

    protected function prepareForValidation(): void
    {
        // For authenticated users, ignore guestInfo (prefilled server-side)
        if (Auth::guard('api')->check() || $this->bearerToken()) {
            $this->request->remove('guestInfo');
            return;
        }

        $guestInfo = $this->input('guestInfo', []);

        // Map client-provided username to fullName if fullName is missing
        if (is_array($guestInfo)) {
            if (empty($guestInfo['fullName']) && !empty($guestInfo['username'])) {
                $guestInfo['fullName'] = $guestInfo['username'];
            }

            $this->merge([
                'guestInfo' => $guestInfo,
            ]);
        }
    }
}
