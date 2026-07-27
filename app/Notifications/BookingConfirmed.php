<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'BOOKING',
            'title' => 'Đặt vé thành công',
            'message' => 'Đơn vé ' . substr($this->booking->booking_id, 0, 8) . ' đã được xác nhận. Mã QR đã sẵn sàng.',
            'bookingId' => $this->booking->booking_id,
            'actionUrl' => '/account/account-history/' . $this->booking->booking_id,
        ];
    }
}
