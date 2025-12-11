<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bookingId' => 'required|uuid|exists:bookings,id',
            'paymentMethod' => 'required|in:PAYPAL,MOMO,VNPAY'
        ];
    }
}
