<?php

namespace App\Services;

use App\Enums\SeatStatus;
use App\Models\Showtime;
use App\Models\ShowtimeSeat;
use App\Models\Seat;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShowtimeSeatService
{
    public function __construct(
        protected PriceCalculationService $priceCalculationService,
    ) {}

    public function getById(string $id): ShowtimeSeat
    {
        return ShowtimeSeat::with('seat', 'showtime')
            ->where('showtime_seat_id', $id)
            ->firstOrFail();
    }

    public function updateShowtimeSeat(string $id, array $data): ShowtimeSeat
    {
        /** @var ShowtimeSeat $ss */
        $ss = ShowtimeSeat::where('showtime_seat_id', $id)->firstOrFail();

        if (isset($data['status'])) {
            $ss->status = SeatStatus::from($data['status']);
        }
        if (isset($data['basePrice'])) {
            $ss->base_price = $data['basePrice'];
        }
        if (isset($data['finalPrice'])) {
            $ss->final_price = $data['finalPrice'];
        }
        if (isset($data['priceBreakdown'])) {
            $ss->price_breakdown = $data['priceBreakdown'];
        }

        $ss->save();
        return $ss->fresh(['seat', 'showtime']);
    }

    /** Reset về AVAILABLE */
    public function resetShowtimeSeat(string $id): ShowtimeSeat
    {
        /** @var ShowtimeSeat $ss */
        $ss = ShowtimeSeat::where('showtime_seat_id', $id)->firstOrFail();
        $ss->status = SeatStatus::AVAILABLE;
        $ss->save();

        return $ss->fresh(['seat', 'showtime']);
    }

    public function getSeatsForShowtime(string $showtimeId): Collection
    {
        return ShowtimeSeat::with('seat')
            ->where('showtime_id', $showtimeId)
            ->orderBy('showtime_seat_id')
            ->get();
    }

    public function getAvailableSeatsForShowtime(string $showtimeId): array
    {
        $seats = ShowtimeSeat::with('seat')
            ->where('showtime_id', $showtimeId)
            ->where('status', SeatStatus::AVAILABLE) // enum cast → string
            ->get();

        $dataSeats = $seats->map(function (ShowtimeSeat $ss) {
            $seat = $ss->seat;
            return [
                'showtimeSeatId' => $ss->showtime_seat_id,
                'seatId'         => $seat?->seat_id,
                'rowLabel'       => $seat?->row_label,
                'seatNumber'     => $seat?->seat_number,
                'seatType'       => $seat?->seat_type,
                'status'         => $ss->status?->value,
                'basePrice'      => $ss->base_price,
                'finalPrice'     => $ss->final_price,
            ];
        })->values()->all();

        return [
            'showtimeId'     => $showtimeId,
            'totalAvailable' => count($dataSeats),
            'seats'          => $dataSeats,
        ];
    }

    /**
     * Admin: recalc giá cho toàn bộ ghế showtime
     * POST /showtime-seats/showtime/{showtimeId}/recalculate-prices
     */
    public function recalculatePrices(string $showtimeId): array
    {
        /** @var Showtime $showtime */
        $showtime = Showtime::with('room')->where('showtime_id', $showtimeId)->firstOrFail();

        $showtimeSeats = ShowtimeSeat::with('seat')
            ->where('showtime_id', $showtimeId)
            ->get();

        foreach ($showtimeSeats as $ss) {
            /** @var Seat|null $seat */
            $seat = $ss->seat;
            if (!$seat) {
                continue;
            }

            // dùng PriceCalculationService để tính base + breakdown
            [$price, $breakdownJson] = $this->priceCalculationService
                ->calculatePriceWithBreakdown($showtime, $seat);

            $ss->base_price      = $price;
            $ss->final_price     = $price;        // chưa áp ticket type
            $ss->price_breakdown = json_decode($breakdownJson, true);
            $ss->save();
        }

        return [
            'showtimeId'   => $showtimeId,
            'seatsUpdated' => $showtimeSeats->count(),
        ];
    }
}
