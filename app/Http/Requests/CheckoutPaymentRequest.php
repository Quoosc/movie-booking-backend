<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'paymentMethod' => 'required|string|in:PAYPAL,MOMO,VNPAY',
            'guestInfo' => 'nullable|array',
            'guestInfo.email' => 'required_with:guestInfo|email|max:255',
            'guestInfo.username' => 'required_with:guestInfo|string|max:100',
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
        ];
    }
}
