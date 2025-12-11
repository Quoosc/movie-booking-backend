<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qrCodeUrl' => 'required|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'qrCodeUrl.required' => 'QR Code URL là bắt buộc',
            'qrCodeUrl.url' => 'QR Code URL phải là URL hợp lệ',
            'qrCodeUrl.max' => 'QR Code URL tối đa 500 ký tự',
        ];
    }
}
