<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeatResource;
use App\Models\Room;
use App\Models\Seat;
use App\Models\ShowtimeSeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SeatLayoutService;
use Illuminate\Support\Facades\Log;

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

    protected SeatLayoutService $seatLayoutService;

    public function __construct(SeatLayoutService $seatLayoutService)
    {
        $this->seatLayoutService = $seatLayoutService;
    }

    // ========== BASIC CRUD ==========

    // GET /seats
    public function index()
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $seats = Seat::with('room.cinema')
            ->orderBy('room_id')
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        return $this->respond(SeatResource::collection($seats));
    }

    // GET /seats/{seatId}
    public function show(string $seatId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $seat = Seat::with('room.cinema')->findOrFail($seatId);

        return $this->respond(new SeatResource($seat));
    }

    // POST /seats
    public function store(Request $request)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $data = $request->validate([
            'roomId'     => 'required|exists:rooms,room_id',
            'seatNumber' => 'required|integer|min:1',
            'rowLabel'   => 'required|string|max:5',
            'seatType'   => 'required|in:NORMAL,VIP,COUPLE',
        ]);

        $seat = Seat::create([
            'room_id'     => $data['roomId'],
            'seat_number' => $data['seatNumber'],
            'row_label'   => strtoupper($data['rowLabel']),
            'seat_type'   => strtoupper($data['seatType']),
        ]);

        $seat->load('room.cinema');

        return $this->respond(new SeatResource($seat), 'Seat created', Response::HTTP_CREATED);
    }

    // PUT /seats/{seatId}
    public function update(string $seatId, Request $request)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $seat = Seat::findOrFail($seatId);

        $data = $request->validate([
            'seatNumber' => 'sometimes|integer|min:1',
            'rowLabel'   => 'sometimes|string|max:5',
            'seatType'   => 'sometimes|in:NORMAL,VIP,COUPLE',
        ]);

        if (array_key_exists('seatNumber', $data)) {
            $seat->seat_number = $data['seatNumber'];
        }
        if (array_key_exists('rowLabel', $data)) {
            $seat->row_label = strtoupper($data['rowLabel']);
        }
        if (array_key_exists('seatType', $data)) {
            $seat->seat_type = strtoupper($data['seatType']);
        }

        $seat->save();
        $seat->load('room.cinema');

        return $this->respond(new SeatResource($seat), 'Seat updated');
    }

    // DELETE /seats/{seatId}
    public function destroy(string $seatId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $seat = Seat::findOrFail($seatId);
        $seat->delete();

        return $this->respond(null, 'Seat deleted', Response::HTTP_NO_CONTENT);
    }

    // ========== TOOLS ==========

    // GET /seats/room/{roomId}
    public function getByRoom(string $roomId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $room = Room::with('cinema')->findOrFail($roomId);

        $seats = Seat::with('room.cinema')
            ->where('room_id', $roomId)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        $data = [
            'roomId'    => $room->room_id,
            'roomName'  => $room->name ?? $room->room_number ?? null,
            'cinemaId'  => $room->cinema?->cinema_id,
            'cinemaName' => $room->cinema?->name,
            'totalSeats' => $seats->count(),
            'seats'     => SeatResource::collection($seats),
        ];

        return $this->respond($data);
    }

    // POST /seats/generate
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

        DB::transaction(function () use (
            $room,
            $rows,
            $seatsPerRow,
            $vipRows,
            $coupleRows,
            &$normalCount,
            &$vipCount,
            &$coupleCount
        ) {
            for ($i = 0; $i < $rows; $i++) {
                $rowLabel = chr(ord('A') + $i);

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
        });

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

    // GET /seats/row-labels?rows=10
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

    // ==========================================================
    // PUBLIC: GET /seats/layout?showtime_id=... hoặc showtimeId=...
    // ==========================================================
    public function layout(Request $request)
    {
        $showtimeId = $request->query('showtime_id') ?? $request->query('showtimeId');

        if (!$showtimeId) {
            return $this->respond(null, 'showtime_id or showtimeId is required', 400);
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $showtimeId)) {
            return $this->respond(null, 'Invalid showtimeId format', 400);
        }

        try {
            $rows = ShowtimeSeat::query()
                ->join('seats', 'seats.seat_id', '=', 'showtime_seats.seat_id')
                ->where('showtime_seats.showtime_id', $showtimeId)
                ->select(
                    'showtime_seats.showtime_seat_id',
                    'seats.seat_id',
                    'seats.row_label',
                    'seats.seat_number',
                    'seats.seat_type',
                    'showtime_seats.seat_status',
                    'showtime_seats.price'
                )
                ->orderBy('seats.row_label')
                ->orderBy('seats.seat_number')
                ->get();

            $data = $rows->map(function ($r) {
                return [

                    'showtimeSeatId' => (string) $r->showtime_seat_id,
                    'seatId' => (string) $r->seat_id,

                    'row'    => $r->row_label,
                    'number' => (int) $r->seat_number,
                    'type'   => $r->seat_type,
                    'status' => $r->seat_status,
                    'price'  => $r->price !== null ? (float) $r->price : 0,
                ];
            })->all();

            return $this->respond($data);
        } catch (\Exception $e) {
            Log::error('Seat layout error: ' . $e->getMessage(), [
                'showtimeId' => $showtimeId,
                'exception' => $e,
            ]);
            return $this->respond(null, 'Server error', 500);
        }
    }
}
