<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'showtimeId' => 'required|uuid|exists:showtimes,showtime_id',
            'seats' => 'required|array|min:1',
            'seats.*.showtimeSeatId' => 'required|uuid|exists:showtime_seats,showtime_seat_id',
            'seats.*.ticketTypeId' => 'required|uuid|exists:ticket_types,id',
            'promotionIds' => 'nullable|array',
            'promotionIds.*' => 'uuid|exists:promotions,promotion_id',
            'paymentMethod' => 'required|in:PAYPAL,MOMO,VNPAY'
        ];
    }
}
