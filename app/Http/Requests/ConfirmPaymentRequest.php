<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transactionId' => 'required|string',
            'paymentMethod' => 'required|in:PAYPAL,MOMO,VNPAY'
        ];
    }
}
