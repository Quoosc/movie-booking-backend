<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeatResource;
use App\Models\Room;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SeatController extends Controller
{
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function ensureAdmin()
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'ADMIN') {
            return $this->respond(null, 'Admin access required', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    // ========== POST /seats/generate ==========
    public function generate(Request $request)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $data = $request->validate([
            'roomId'      => 'required|exists:rooms,room_id',
            'rows'        => 'required|integer|min:1|max:100',
            'seatsPerRow' => 'required|integer|min:1|max:50',
            'vipRows'     => 'array',
            'vipRows.*'   => 'string',
            'coupleRows'  => 'array',
            'coupleRows.*' => 'string',
        ]);

        $room = Room::with('cinema')->findOrFail($data['roomId']);

        // Không cho generate nếu room đã có ghế
        if ($room->seats()->exists()) {
            return $this->respond(
                null,
                'Room already has seats. Please clear seats before generating.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $rows        = (int) $data['rows'];
        $seatsPerRow = (int) $data['seatsPerRow'];
        $vipRows     = collect($data['vipRows'] ?? [])->map(fn($r) => strtoupper($r))->all();
        $coupleRows  = collect($data['coupleRows'] ?? [])->map(fn($r) => strtoupper($r))->all();

        $normalCount = $vipCount = $coupleCount = 0;

        for ($i = 0; $i < $rows; $i++) {
            $rowLabel = chr(ord('A') + $i); // A, B, C, ...

            for ($num = 1; $num <= $seatsPerRow; $num++) {
                if (in_array($rowLabel, $coupleRows, true)) {
                    $seatType = 'COUPLE';
                    $coupleCount++;
                } elseif (in_array($rowLabel, $vipRows, true)) {
                    $seatType = 'VIP';
                    $vipCount++;
                } else {
                    $seatType = 'NORMAL';
                    $normalCount++;
                }

                Seat::create([
                    'room_id'     => $room->room_id,
                    'seat_number' => $num,
                    'row_label'   => $rowLabel,
                    'seat_type'   => $seatType,
                ]);
            }
        }

        $seats = Seat::with('room.cinema')
            ->where('room_id', $room->room_id)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        $response = [
            'totalSeatsCreated' => $seats->count(),
            'normalSeats'       => $normalCount,
            'vipSeats'          => $vipCount,
            'coupleSeats'       => $coupleCount,
            'seats'             => SeatResource::collection($seats),
        ];

        return $this->respond($response, 'Seats generated successfully');
    }

    // ========== GET /seats/row-labels?rows=10 ==========
    public function rowLabels(Request $request)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $rows = (int) $request->query('rows', 10);
        $rows = max(1, min(100, $rows));

        $labels = [];
        for ($i = 0; $i < $rows; $i++) {
            $labels[] = chr(ord('A') + $i);
        }

        $data = [
            'numberOfRows' => $rows,
            'labels'       => $labels,
        ];

        return $this->respond($data);
    }
}
