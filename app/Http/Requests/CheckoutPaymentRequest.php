<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CheckoutPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockId' => 'required|uuid|exists:seat_locks,seat_lock_id',
            'promotionCode' => 'nullable|string|max:50',
            'snackCombos' => 'nullable|array',
            'snackCombos.*.snackId' => 'required|uuid|exists:snacks,snack_id',
            'snackCombos.*.quantity' => 'required|integer|min:1|max:10',
            'paymentMethod' => 'required|string|in:PAYPAL,MOMO',
            'guestInfo' => 'nullable|array',
            'guestInfo.email' => 'required_with:guestInfo|email|max:255',
            'guestInfo.fullName' => 'required_with:guestInfo|required_without:guestInfo.username|string|max:255',
            'guestInfo.username' => 'required_with:guestInfo|required_without:guestInfo.fullName|string|max:255',
            'guestInfo.phoneNumber' => 'required_with:guestInfo|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'lockId.required' => 'Lock ID là bắt buộc',
            'lockId.uuid' => 'Lock ID phải là UUID hợp lệ',
            'lockId.exists' => 'Lock không tồn tại hoặc đã hết hạn',
            'paymentMethod.required' => 'Phương thức thanh toán là bắt buộc',
            'paymentMethod.in' => 'Phương thức thanh toán không hợp lệ',
            'guestInfo.fullName.required_without' => 'Họ tên hoặc username là bắt buộc cho khách',
            'guestInfo.username.required_with' => 'Username là bắt buộc cho khách nếu thiếu họ tên',
            'guestInfo.username.required_without' => 'Username hoặc họ tên là bắt buộc cho khách',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Authenticated users shouldn't need guest info; drop it to avoid validation
        if (Auth::guard('api')->check()) {
            $this->request->remove('guestInfo');
            return;
        }

        $guestInfo = $this->input('guestInfo', []);

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
