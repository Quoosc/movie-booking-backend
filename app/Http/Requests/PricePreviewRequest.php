<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PricePreviewRequest extends FormRequest
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
            'snacks' => 'nullable|array',
            'snacks.*.snackId' => 'required|uuid|exists:snacks,snack_id',
            'snacks.*.quantity' => 'required|integer|min:1|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'lockId.required' => 'Lock ID là bắt buộc',
            'lockId.uuid' => 'Lock ID phải là UUID hợp lệ',
            'lockId.exists' => 'Lock không tồn tại hoặc đã hết hạn',
            'promotionCode.string' => 'Mã khuyến mãi phải là chuỗi',
            'promotionCode.max' => 'Mã khuyến mãi tối đa 50 ký tự',
            'snacks.array' => 'Danh sách snacks phải là mảng',
            'snacks.*.snackId.required' => 'Snack ID là bắt buộc',
            'snacks.*.snackId.uuid' => 'Snack ID phải là UUID hợp lệ',
            'snacks.*.snackId.exists' => 'Snack không tồn tại',
            'snacks.*.quantity.required' => 'Số lượng là bắt buộc',
            'snacks.*.quantity.integer' => 'Số lượng phải là số nguyên',
            'snacks.*.quantity.min' => 'Số lượng tối thiểu là 1',
            'snacks.*.quantity.max' => 'Số lượng tối đa là 10',
        ];
    }
}
