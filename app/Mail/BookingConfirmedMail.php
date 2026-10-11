<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        $this->booking->load([
            'user',
            'showtime.movie',
            'showtime.room.cinema',
            'bookingSeats.showtimeSeat.seat',
            'bookingSnacks.snack',
        ]);

        $movieTitle = $this->booking->showtime?->movie?->title ?? 'Bộ phim';
        $subject    = "[Cineon] Đặt vé thành công – {$movieTitle}";

        return $this->subject($subject)
            ->view('emails.booking.confirmed', [
                'booking' => $this->booking,
            ]);
    }
}
