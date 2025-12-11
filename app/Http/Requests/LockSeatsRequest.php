<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'showtimeId' => 'required|uuid|exists:showtimes,showtime_id',
            'seats' => 'required|array|min:1|max:10',
            'seats.*.showtimeSeatId' => 'required|uuid|exists:showtime_seats,showtime_seat_id',
            'seats.*.ticketTypeId' => 'required|uuid|exists:ticket_types,id',
        ];
    }

    public function messages(): array
    {
        return [
            'showtimeId.required' => 'Showtime ID là bắt buộc',
            'showtimeId.uuid' => 'Showtime ID phải là UUID hợp lệ',
            'showtimeId.exists' => 'Showtime không tồn tại',
            'seats.required' => 'Danh sách ghế là bắt buộc',
            'seats.array' => 'Danh sách ghế phải là mảng',
            'seats.min' => 'Phải chọn ít nhất 1 ghế',
            'seats.max' => 'Tối đa 10 ghế mỗi lần đặt',
            'seats.*.showtimeSeatId.required' => 'Showtime Seat ID là bắt buộc',
            'seats.*.showtimeSeatId.uuid' => 'Showtime Seat ID phải là UUID hợp lệ',
            'seats.*.showtimeSeatId.exists' => 'Ghế không tồn tại',
            'seats.*.ticketTypeId.required' => 'Ticket Type ID là bắt buộc',
            'seats.*.ticketTypeId.uuid' => 'Ticket Type ID phải là UUID hợp lệ',
            'seats.*.ticketTypeId.exists' => 'Loại vé không tồn tại',
        ];
    }
}
