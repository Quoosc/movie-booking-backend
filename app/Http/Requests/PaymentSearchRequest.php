<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bookingId' => 'nullable|uuid|exists:bookings,id',
            'status' => 'nullable|in:PENDING,COMPLETED,FAILED,REFUNDED,CANCELLED,REFUND_PENDING',
            'method' => 'nullable|in:PAYPAL,MOMO,VNPAY',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate'
        ];
    }
}
