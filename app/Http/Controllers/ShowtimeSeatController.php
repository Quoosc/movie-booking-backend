<?php

namespace App\Http\Controllers;

use App\Enums\SeatStatus;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\ShowtimeSeat;
use App\Services\PriceCalculationService;
use App\Transformers\ShowtimeSeatTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShowtimeSeatController extends Controller
{
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // ========== GET /showtime-seats/{id} ==========
    public function getById(string $id)
    {
        /** @var ShowtimeSeat|null $showtimeSeat */
        $showtimeSeat = ShowtimeSeat::with('seat')->find($id);

        if (!$showtimeSeat) {
            return $this->respond(null, 'Showtime seat not found', Response::HTTP_NOT_FOUND);
        }

        return $this->respond(
            ShowtimeSeatTransformer::toDataResponse($showtimeSeat)
        );
    }

    // ========== GET /showtime-seats/showtime/{showtimeId} ==========
    public function getByShowtime(string $showtimeId)
    {
        $seats = ShowtimeSeat::query()
            ->join('seats', 'seats.seat_id', '=', 'showtime_seats.seat_id')
            ->where('showtime_seats.showtime_id', $showtimeId)
            ->select('showtime_seats.*')     // tránh override field
            ->orderBy('seats.row_label')
            ->orderBy('seats.seat_number')
            ->with('seat')
            ->get();

        $data = $seats
            ->map(fn (ShowtimeSeat $ss) => ShowtimeSeatTransformer::toDataResponse($ss))
            ->all();

        return $this->respond($data);
    }

    // ========== GET /showtime-seats/showtime/{showtimeId}/available ==========
    public function getAvailableByShowtime(string $showtimeId)
    {
        $seats = ShowtimeSeat::query()
            ->join('seats', 'seats.seat_id', '=', 'showtime_seats.seat_id')
            ->where('showtime_seats.showtime_id', $showtimeId)
            ->where('showtime_seats.status', SeatStatus::AVAILABLE->value)
            ->select('showtime_seats.*')
            ->orderBy('seats.row_label')
            ->orderBy('seats.seat_number')
            ->with('seat')
            ->get();

        $data = $seats
            ->map(fn (ShowtimeSeat $ss) => ShowtimeSeatTransformer::toDataResponse($ss))
            ->all();

        return $this->respond($data);
    }

    // ========== PUT /showtime-seats/{id} ==========
    public function update(string $id, Request $request)
    {
        /** @var ShowtimeSeat|null $showtimeSeat */
        $showtimeSeat = ShowtimeSeat::with('seat')->find($id);

        if (!$showtimeSeat) {
            return $this->respond(null, 'Showtime seat not found', Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'status' => 'nullable|string',
            'price'  => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $showtimeSeat->status = $data['status'];
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $showtimeSeat->price = $data['price'];
        }

        $showtimeSeat->save();

        return $this->respond(
            ShowtimeSeatTransformer::toDataResponse($showtimeSeat)
        );
    }

    // ========== PUT /showtime-seats/{id}/reset ==========
    public function reset(string $id)
    {
        /** @var ShowtimeSeat|null $showtimeSeat */
        $showtimeSeat = ShowtimeSeat::with('seat')->find($id);

        if (!$showtimeSeat) {
            return $this->respond(null, 'Showtime seat not found', Response::HTTP_NOT_FOUND);
        }

        $showtimeSeat->status = SeatStatus::AVAILABLE->value;
        $showtimeSeat->save();

        return $this->respond(
            ShowtimeSeatTransformer::toDataResponse($showtimeSeat)
        );
    }

    // ========== POST /showtime-seats/showtime/{showtimeId}/recalculate-prices ==========
    // nếu đã có thì chỉ update lại giá + breakdown.
    public function recalculatePrices(
        string $showtimeId,
        PriceCalculationService $priceCalculationService
    ) {
        /** @var Showtime|null $showtime */
        $showtime = Showtime::with('room.seats')->find($showtimeId);

        if (!$showtime) {
            return $this->respond(null, 'Showtime not found', Response::HTTP_NOT_FOUND);
        }

        $room = $showtime->room;
        if (!$room) {
            return $this->respond(null, 'Showtime has no room assigned', Response::HTTP_BAD_REQUEST);
        }

        $roomSeats = $room->seats()
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        if ($roomSeats->isEmpty()) {
            return $this->respond([], 'Room has no seats defined', Response::HTTP_BAD_REQUEST);
        }

        $result = [];

        DB::transaction(function () use (
            $roomSeats,
            $showtime,
            $priceCalculationService,
            &$result
        ) {
            foreach ($roomSeats as $seat) {
                /** @var Seat $seat */

                // Tìm hoặc tạo mới showtime_seat cho từng ghế
                /** @var ShowtimeSeat $showtimeSeat */
                $showtimeSeat = ShowtimeSeat::firstOrNew([
                    'showtime_id' => $showtime->showtime_id,
                    'seat_id'     => $seat->seat_id,
                ]);

                if (!$showtimeSeat->exists) {
                    $showtimeSeat->status = SeatStatus::AVAILABLE->value;
                }

                // Tính giá & breakdown
                [$price, $breakdownJson] = $priceCalculationService
                    ->calculatePriceWithBreakdown($showtime, $seat);

                $showtimeSeat->price           = $price;
                $showtimeSeat->price_breakdown = $breakdownJson;
                $showtimeSeat->save();

                $showtimeSeat->setRelation('seat', $seat);

                $result[] = ShowtimeSeatTransformer::toDataResponse($showtimeSeat);
            }
        });

        return $this->respond($result, 'Prices recalculated for all seats of this showtime');
    }
}
