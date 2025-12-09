<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShowtimeResource;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShowtimeController extends Controller
{
    // ======== COMMON RESPONSE (giống các controller khác) ========
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // ======== HELPER TÌM ENTITY ========

    protected function findShowtimeOrFail(string $showtimeId): Showtime
    {
        return Showtime::with(['room', 'movie'])->findOrFail($showtimeId);
    }

    protected function findRoomOrFail(string $roomId): Room
    {
        return Room::findOrFail($roomId);
    }

    protected function findMovieOrFail(string $movieId): Movie
    {
        return Movie::findOrFail($movieId);
    }

    /**
     * Validate không bị trùng giờ chiếu trong cùng phòng
     * Clone logic validateNoOverlap bên Spring
     */
    protected function validateNoOverlap(?string $showtimeId, string $roomId, Carbon $startTime, int $movieDurationMinutes): void
    {
        $endTime = (clone $startTime)->addMinutes($movieDurationMinutes);

        $query = Showtime::with('movie')
            ->where('room_id', $roomId);

        if ($showtimeId) {
            $query->where('showtime_id', '!=', $showtimeId);
        }

        $existing = $query->get();

        foreach ($existing as $st) {
            if (!$st->movie) {
                continue;
            }

            /** @var Carbon $stStart */
            $stStart = $st->start_time instanceof Carbon
                ? $st->start_time
                : Carbon::parse($st->start_time);

            $stEnd = (clone $stStart)->addMinutes($st->movie->duration);

            // khoảng [stStart, stEnd] overlap với [startTime, endTime] ?
            if ($stStart < $endTime && $stEnd > $startTime) {
                abort(
                    Response::HTTP_BAD_REQUEST,
                    'This showtime overlaps with another showtime in the same room'
                );
            }
        }
    }

    // =================================================================
    //  ADMIN ONLY – POST /showtimes  (AddShowtimeRequest)
    // =================================================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'roomId'    => 'required|uuid|exists:rooms,room_id',
            'movieId'   => 'required|uuid|exists:movies,movie_id',
            'format'    => 'required|string|max:50',
            'startTime' => 'required|date',
        ]);

        $room  = $this->findRoomOrFail($data['roomId']);
        $movie = $this->findMovieOrFail($data['movieId']);

        $startTime = Carbon::parse($data['startTime']);

        // validate không trùng
        $this->validateNoOverlap(null, $room->room_id, $startTime, $movie->duration);

        $showtime = Showtime::create([
            'room_id'    => $room->room_id,
            'movie_id'   => $movie->movie_id,
            'format'     => $data['format'],
            'start_time' => $startTime,
        ]);

        // TODO: generate showtime_seats giống showtimeSeatService.generateShowtimeSeats()
        // Tạm thời chưa code phần này thì comment lại

        $showtime->load(['room', 'movie']);

        return $this->respond(
            new ShowtimeResource($showtime),
            'Showtime created',
            Response::HTTP_CREATED
        );
    }

    // =================================================================
    //  ADMIN – PUT /showtimes/{showtimeId} (UpdateShowtimeRequest)
    // =================================================================
    public function update(string $showtimeId, Request $request)
    {
        $showtime = $this->findShowtimeOrFail($showtimeId);

        $data = $request->validate([
            'roomId'    => 'nullable|uuid|exists:rooms,room_id',
            'movieId'   => 'nullable|uuid|exists:movies,movie_id',
            'format'    => 'nullable|string|max:50',
            'startTime' => 'nullable|date',
        ]);

        $newRoomId = $data['roomId']    ?? $showtime->room_id;
        $newMovieId = $data['movieId']  ?? $showtime->movie_id;
        $newStartTime = isset($data['startTime'])
            ? Carbon::parse($data['startTime'])
            : ($showtime->start_time instanceof Carbon
                ? $showtime->start_time
                : Carbon::parse($showtime->start_time));

        // movie dùng để tính duration
        $movie = isset($data['movieId'])
            ? $this->findMovieOrFail($newMovieId)
            : $this->findMovieOrFail($showtime->movie_id);

        // Nếu room/movie/startTime có đổi -> check trùng
        if (
            $newRoomId !== $showtime->room_id ||
            $newMovieId !== $showtime->movie_id ||
            !$newStartTime->equalTo(
                $showtime->start_time instanceof Carbon
                    ? $showtime->start_time
                    : Carbon::parse($showtime->start_time)
            )
        ) {
            $this->validateNoOverlap($showtimeId, $newRoomId, $newStartTime, $movie->duration);
        }

        if (isset($data['roomId'])) {
            $showtime->room_id = $data['roomId'];
        }
        if (isset($data['movieId'])) {
            $showtime->movie_id = $data['movieId'];
        }
        if (isset($data['format'])) {
            $showtime->format = $data['format'];
        }
        if (isset($data['startTime'])) {
            $showtime->start_time = $newStartTime;
        }

        $showtime->save();
        $showtime->load(['room', 'movie']);

        return $this->respond(
            new ShowtimeResource($showtime),
            'Showtime updated'
        );
    }

    // =================================================================
    //  ADMIN – DELETE /showtimes/{showtimeId}
    // =================================================================
    public function destroy(string $showtimeId)
    {
        $showtime = Showtime::findOrFail($showtimeId);

        // 1) còn seat lock active ?
        $hasSeatLocks = DB::table('seat_locks')
            ->where('showtime_id', $showtimeId)
            ->where('active', true)
            ->exists();

        if ($hasSeatLocks) {
            return $this->respond(
                null,
                'Cannot delete showtime with active seat locks',
                Response::HTTP_BAD_REQUEST
            );
        }

        // 2) còn booking ?
        $hasBookings = DB::table('bookings')
            ->where('showtime_id', $showtimeId)
            ->exists();

        if ($hasBookings) {
            return $this->respond(
                null,
                'Cannot delete showtime with existing bookings',
                Response::HTTP_BAD_REQUEST
            );
        }

        // 3) có seat nào không phải AVAILABLE ?
        $hasNonAvailableSeats = DB::table('showtime_seats')
            ->where('showtime_id', $showtimeId)
            ->where('seat_status', '<>', 'AVAILABLE')
            ->exists();

        if ($hasNonAvailableSeats) {
            return $this->respond(
                null,
                'Cannot delete showtime with non-available seats',
                Response::HTTP_BAD_REQUEST
            );
        }

        // nếu muốn chắc chắn có thể xóa luôn showtime_seats, nhưng FK cascade đã đủ
        // DB::table('showtime_seats')->where('showtime_id', $showtimeId)->delete();

        $showtime->delete();

        return $this->respond(null, 'Showtime deleted');
    }

    // =================================================================
    //  PUBLIC – GET /showtimes/{showtimeId}
    // =================================================================
    public function show(string $showtimeId)
    {
        $showtime = $this->findShowtimeOrFail($showtimeId);

        return $this->respond(
            new ShowtimeResource($showtime),
            'OK'
        );
    }

    // PUBLIC – GET /showtimes
    public function index()
    {
        $showtimes = Showtime::with(['room', 'movie'])
            ->orderBy('start_time')
            ->get();

        return $this->respond(
            ShowtimeResource::collection($showtimes),
            'OK'
        );
    }

    // PUBLIC – GET /showtimes/movie/{movieId}
    public function byMovie(string $movieId)
    {
        $this->findMovieOrFail($movieId);

        $showtimes = Showtime::with(['room', 'movie'])
            ->where('movie_id', $movieId)
            ->orderBy('start_time')
            ->get();

        return $this->respond(
            ShowtimeResource::collection($showtimes),
            'OK'
        );
    }

    // PUBLIC – GET /showtimes/movie/{movieId}/upcoming
    public function upcomingByMovie(string $movieId)
    {
        $this->findMovieOrFail($movieId);

        $now = Carbon::now();

        $showtimes = Showtime::with(['room', 'movie'])
            ->where('movie_id', $movieId)
            ->where('start_time', '>=', $now)
            ->orderBy('start_time')
            ->get();

        return $this->respond(
            ShowtimeResource::collection($showtimes),
            'OK'
        );
    }

    // PUBLIC – GET /showtimes/room/{roomId}
    public function byRoom(string $roomId)
    {
        $this->findRoomOrFail($roomId);

        $showtimes = Showtime::with(['room', 'movie'])
            ->where('room_id', $roomId)
            ->orderBy('start_time')
            ->get();

        return $this->respond(
            ShowtimeResource::collection($showtimes),
            'OK'
        );
    }

    // PUBLIC – GET /showtimes/movie/{movieId}/date-range?startDate=...&endDate=...
    public function byMovieAndDateRange(string $movieId, Request $request)
    {
        $this->findMovieOrFail($movieId);

        $data = $request->validate([
            'startDate' => 'required|date',
            'endDate'   => 'required|date|after_or_equal:startDate',
        ]);

        $startDate = Carbon::parse($data['startDate']);
        $endDate   = Carbon::parse($data['endDate']);

        $showtimes = Showtime::with(['room', 'movie'])
            ->where('movie_id', $movieId)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->orderBy('start_time')
            ->get();

        return $this->respond(
            ShowtimeResource::collection($showtimes),
            'OK'
        );
    }
}
