<?php

namespace App\Services;

use App\Enums\SeatStatus;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\ShowtimeSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeatService
{
    public function getAllSeats(): Collection
    {
        return Seat::with('room.cinema')
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();
    }

    public function getSeatById(string $seatId): Seat
    {
        return Seat::with('room.cinema')->where('seat_id', $seatId)->firstOrFail();
    }

    public function createSeat(array $data): Seat
    {
        $seatId = $data['seatId'] ?? (string) Str::uuid();

        return Seat::create([
            'seat_id'     => $seatId,
            'room_id'     => $data['roomId'],
            'row_label'   => $data['rowLabel'],
            'seat_number' => $data['seatNumber'],
            'seat_type'   => $data['seatType'], // NORMAL / VIP / COUPLE
        ]);
    }

    public function updateSeat(string $seatId, array $data): Seat
    {
        /** @var Seat $seat */
        $seat = Seat::where('seat_id', $seatId)->firstOrFail();

        if (isset($data['rowLabel'])) {
            $seat->row_label = $data['rowLabel'];
        }
        if (isset($data['seatNumber'])) {
            $seat->seat_number = $data['seatNumber'];
        }
        if (isset($data['seatType'])) {
            $seat->seat_type = $data['seatType'];
        }

        $seat->save();
        return $seat->fresh('room.cinema');
    }

    public function deleteSeat(string $seatId): void
    {
        $seat = Seat::where('seat_id', $seatId)->firstOrFail();

        // tuỳ logic: nếu seat đang dùng trong showtime_seats thì không cho xoá
        if ($seat->showtimeSeats()->exists()) {
            // có thể soft delete hoặc throw exception
            // ở đây mình cứ đơn giản: không xoá, tuỳ bạn custom thêm
            throw new \RuntimeException('Seat is used in showtime seats, cannot delete.');
        }

        $seat->delete();
    }

    public function getSeatsByRoom(string $roomId): Collection
    {
        return Seat::with('room.cinema')
            ->where('room_id', $roomId)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();
    }

    /**
     * /seats/layout?showtimeId=...
     * Layout ghế cho FE booking.
     */
    public function getSeatLayoutForShowtime(string $showtimeId): array
    {
        /** @var Showtime $showtime */
        $showtime = Showtime::with(['room.cinema', 'showtimeSeats.seat'])
            ->where('showtime_id', $showtimeId)
            ->firstOrFail();

        $room   = $showtime->room;
        $cinema = $room?->cinema;

        /** @var Collection $showtimeSeats */
        $showtimeSeats = $showtime->showtimeSeats;

        // group theo row_label
        $rows = [];

        foreach ($showtimeSeats as $ss) {
            $seat = $ss->seat;
            if (!$seat) continue;

            $rowLabel = $seat->row_label;

            if (!isset($rows[$rowLabel])) {
                $rows[$rowLabel] = [
                    'rowLabel' => $rowLabel,
                    'seats'    => [],
                ];
            }

            $rows[$rowLabel]['seats'][] = [
                'showtimeSeatId' => $ss->showtime_seat_id,
                'seatId'         => $seat->seat_id,
                'rowLabel'       => $seat->row_label,
                'seatNumber'     => $seat->seat_number,
                'seatType'       => $seat->seat_type,
                'status'         => $ss->status?->value ?? SeatStatus::AVAILABLE->value,
                'basePrice'      => $ss->base_price,
                'finalPrice'     => $ss->final_price,
            ];
        }

        // sort theo row A, B, C...
        ksort($rows);

        return [
            'showtimeId' => $showtime->showtime_id,
            'roomId'     => $room?->room_id,
            'cinemaId'   => $cinema?->cinema_id,
            'roomName'   => $room?->name,
            'cinemaName' => $cinema?->name,
            'rows'       => array_values($rows),
        ];
    }
}
