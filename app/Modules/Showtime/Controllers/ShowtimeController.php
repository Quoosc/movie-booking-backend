<?php

namespace App\Modules\Showtime\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Showtime\Resources\ShowtimeResource;
use App\Models\Movie;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\ShowtimeSeat;
use App\Enums\SeatStatus;
use App\Modules\Pricing\Services\PriceCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ShowtimeController extends BaseController
{
    private PriceCalculationService $priceCalculationService;

    public function __construct(PriceCalculationService $priceCalculationService)
    {
        $this->priceCalculationService = $priceCalculationService;
    }

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

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

    protected function validateNoOverlap(?string $showtimeId, string $roomId, Carbon $startTime, int $movieDurationMinutes): void
    {
        $endTime = (clone $startTime)->addMinutes($movieDurationMinutes);

        $query = Showtime::with('movie')
            ->where('room_id', $roomId);

        if ($showtimeId) {
            $query->where('showtime_id', '!=', $showtimeId);
        }

        $existing = $query->lockForUpdate()->get();

        foreach ($existing as $st) {
            if (!$st->movie) continue;

            $stStart = $st->start_time instanceof Carbon
                ? $st->start_time
                : Carbon::parse($st->start_time);

            $stEnd = (clone $stStart)->addMinutes($st->movie->duration);

            if ($stStart < $endTime && $stEnd > $startTime) {
                abort(Response::HTTP_BAD_REQUEST, 'This showtime overlaps with another showtime in the same room');
            }
        }
    }

    protected function generateShowtimeSeats(Showtime $showtime): void
    {
        $roomSeats = Seat::query()
            ->where('room_id', $showtime->room_id)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        if ($roomSeats->isEmpty()) {
            abort(Response::HTTP_BAD_REQUEST, 'Room has no seats. Please generate seats before creating showtime.');
        }

        $showtime->load('room');
        $now = now();
        $rows = [];

        foreach ($roomSeats as $seat) {
            [$price, $breakdown] = $this->priceCalculationService
                ->calculatePriceWithBreakdown($showtime, $seat);

            $rows[] = [
                'showtime_seat_id' => (string) Str::uuid(),
                'seat_id' => $seat->seat_id,
                'showtime_id' => $showtime->showtime_id,
                'seat_status' => SeatStatus::AVAILABLE->value,
                'price' => $price,
                'price_breakdown' => $breakdown,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('showtime_seats')->insert($rows);
    }

    protected function recalculateShowtimeSeatPrices(Showtime $showtime): void
    {
        $showtime->load('room');

        ShowtimeSeat::with('seat')
            ->where('showtime_id', $showtime->showtime_id)
            ->get()
            ->each(function (ShowtimeSeat $showtimeSeat) use ($showtime) {
                if (!$showtimeSeat->seat) {
                    return;
                }

                [$price, $breakdown] = $this->priceCalculationService
                    ->calculatePriceWithBreakdown($showtime, $showtimeSeat->seat);

                $showtimeSeat->forceFill([
                    'price' => $price,
                    'price_breakdown' => $breakdown,
                ])->save();
            });
    }

    protected function hasBookingActivity(Showtime $showtime): bool
    {
        return $showtime->bookings()->exists()
            || $showtime->seatLocks()->exists()
            || $showtime->showtimeSeats()
                ->where('seat_status', '!=', SeatStatus::AVAILABLE->value)
                ->exists();
    }

    // =========================================================
    // ADMIN – POST /showtimes
    // =========================================================
    public function store(Request $request)
    {
        $data = $request->validate([
            'roomId'    => 'required|uuid|exists:rooms,room_id',
            'movieId'   => 'required|uuid|exists:movies,movie_id',
            'format'    => 'required|string|max:50',
            'startTime' => 'required|date',
        ]);

        $startTime = Carbon::parse($data['startTime']);

        $showtime = DB::transaction(function () use ($data, $startTime) {
            $room = Room::where('room_id', $data['roomId'])->lockForUpdate()->firstOrFail();
            $movie = Movie::where('movie_id', $data['movieId'])->firstOrFail();

            $this->validateNoOverlap(null, $room->room_id, $startTime, $movie->duration);

            $showtime = Showtime::create([
                'room_id' => $room->room_id,
                'movie_id' => $movie->movie_id,
                'format' => $data['format'],
                'start_time' => $startTime,
            ]);

            // Generate all seat inventory inside the same transaction.
            $this->generateShowtimeSeats($showtime);

            return $showtime;
        });

        $showtime->load(['room', 'movie']);

        return $this->respond(
            new ShowtimeResource($showtime),
            'Showtime created',
            Response::HTTP_CREATED
        );
    }

    // =========================================================
    // ADMIN – PUT /showtimes/{showtimeId}
    // =========================================================
    public function update(string $showtimeId, Request $request)
    {
        $data = $request->validate([
            'roomId'    => 'nullable|uuid|exists:rooms,room_id',
            'movieId'   => 'nullable|uuid|exists:movies,movie_id',
            'format'    => 'nullable|string|max:50',
            'startTime' => 'nullable|date',
        ]);

        $showtime = DB::transaction(function () use ($showtimeId, $data) {
            $showtime = Showtime::with(['room', 'movie'])
                ->where('showtime_id', $showtimeId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStartTime = $showtime->start_time instanceof Carbon
                ? $showtime->start_time
                : Carbon::parse($showtime->start_time);
            $newRoomId = $data['roomId'] ?? $showtime->room_id;
            $newMovieId = $data['movieId'] ?? $showtime->movie_id;
            $newFormat = $data['format'] ?? $showtime->format;
            $newStartTime = isset($data['startTime'])
                ? Carbon::parse($data['startTime'])
                : $currentStartTime;

            $roomChanged = $newRoomId !== $showtime->room_id;
            $scheduleChanged = $roomChanged
                || $newMovieId !== $showtime->movie_id
                || !$newStartTime->equalTo($currentStartTime);
            $pricingChanged = $scheduleChanged || $newFormat !== $showtime->format;

            if ($pricingChanged && $this->hasBookingActivity($showtime)) {
                abort(
                    Response::HTTP_CONFLICT,
                    'Cannot change room, movie, format, or start time after booking activity has started.'
                );
            }

            $movie = Movie::where('movie_id', $newMovieId)->firstOrFail();
            Room::where('room_id', $newRoomId)->lockForUpdate()->firstOrFail();

            if ($scheduleChanged) {
                $this->validateNoOverlap($showtimeId, $newRoomId, $newStartTime, $movie->duration);
            }

            $showtime->forceFill([
                'room_id' => $newRoomId,
                'movie_id' => $newMovieId,
                'format' => $newFormat,
                'start_time' => $newStartTime,
            ])->save();

            if ($roomChanged) {
                $showtime->showtimeSeats()->delete();
                $this->generateShowtimeSeats($showtime);
            } elseif ($pricingChanged) {
                $this->recalculateShowtimeSeatPrices($showtime);
            }

            return $showtime;
        });
        $showtime->load(['room', 'movie']);

        return $this->respond(new ShowtimeResource($showtime), 'Showtime updated');
    }

    // (các hàm còn lại index/show/byMovie... giữ nguyên như bạn)



    // =================================================================
    //  ADMIN – PATCH /showtimes/{showtimeId}/toggle-active
    // =================================================================
    public function toggleActive(string $showtimeId)
    {
        $showtime = Showtime::findOrFail($showtimeId);
        $showtime->is_active = !$showtime->is_active;
        $showtime->save();
        $showtime->load(['room', 'movie']);

        $status = $showtime->is_active ? 'activated' : 'deactivated';

        return $this->respond(new ShowtimeResource($showtime), "Showtime {$status}");
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

    // PUBLIC – GET /showtimes  (admin dùng — trả tất cả kể cả inactive)
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

    // PUBLIC – GET /showtimes/search
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'date' => ['nullable', 'date_format:Y-m-d', 'required_with:from,to'],
            'from' => ['nullable', 'date_format:H:i'],
            'to' => ['nullable', 'date_format:H:i', 'after_or_equal:from'],
            'city' => 'nullable|string|max:120',
            'cinemaId' => 'nullable|uuid|exists:cinemas,cinema_id',
            'movieId' => 'nullable|uuid|exists:movies,movie_id',
            'format' => 'nullable|string|max:50',
            'minSeats' => 'nullable|integer|min:1|max:20',
            'page' => 'nullable|integer|min:1',
            'perPage' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Showtime::query()
            ->with(['movie', 'room.cinema'])
            ->withCount('showtimeSeats as total_seats_count')
            ->withCount([
                'showtimeSeats as available_seats_count' => fn ($seatQuery) =>
                    $seatQuery->where('seat_status', SeatStatus::AVAILABLE->value),
            ])
            ->withMin('showtimeSeats as minimum_price', 'price')
            ->where('is_active', true)
            ->where('start_time', '>=', now());

        if (!empty($data['q'])) {
            $term = '%'.trim($data['q']).'%';
            $query->where(function ($nested) use ($term) {
                $nested->whereHas('movie', fn ($movieQuery) =>
                    $movieQuery->where('title', 'like', $term))
                    ->orWhereHas('room.cinema', fn ($cinemaQuery) =>
                        $cinemaQuery->where('name', 'like', $term)
                            ->orWhere('address', 'like', $term));
            });
        }

        if (!empty($data['date'])) {
            $cinemaTimezone = config('cinema.timezone', 'Asia/Ho_Chi_Minh');
            $localDate = Carbon::createFromFormat('Y-m-d', $data['date'], $cinemaTimezone);
            $localStart = !empty($data['from'])
                ? Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['from'], $cinemaTimezone)
                : $localDate->copy()->startOfDay();
            $localEnd = !empty($data['to'])
                ? Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['to'], $cinemaTimezone)->endOfMinute()
                : $localDate->copy()->endOfDay();

            $query->whereBetween('start_time', [
                $localStart->setTimezone('UTC'),
                $localEnd->setTimezone('UTC'),
            ]);
        }
        if (!empty($data['movieId'])) {
            $query->where('movie_id', $data['movieId']);
        }
        if (!empty($data['cinemaId'])) {
            $query->whereHas('room', fn ($roomQuery) =>
                $roomQuery->where('cinema_id', $data['cinemaId']));
        }
        if (!empty($data['city'])) {
            $query->whereHas('room.cinema', fn ($cinemaQuery) =>
                $cinemaQuery->where('city', $data['city']));
        }
        if (!empty($data['format'])) {
            $query->where('format', $data['format']);
        }
        if (!empty($data['minSeats'])) {
            $query->whereHas(
                'showtimeSeats',
                fn ($seatQuery) => $seatQuery->where(
                    'seat_status',
                    SeatStatus::AVAILABLE->value
                ),
                '>=',
                (int) $data['minSeats']
            );
        }

        $paginator = $query
            ->orderBy('start_time')
            ->paginate((int) ($data['perPage'] ?? 30));

        $items = collect($paginator->items())->map(function (Showtime $showtime) {
            $movie = $showtime->movie;
            $room = $showtime->room;
            $cinema = $room?->cinema;

            return [
                'showtimeId' => $showtime->showtime_id,
                'startTime' => $showtime->start_time?->toISOString(),
                'format' => $showtime->format,
                'availableSeats' => (int) ($showtime->available_seats_count ?? 0),
                'totalSeats' => (int) ($showtime->total_seats_count ?? 0),
                'minimumPrice' => $showtime->minimum_price !== null
                    ? (float) $showtime->minimum_price
                    : null,
                'movie' => [
                    'movieId' => $movie?->movie_id,
                    'title' => $movie?->title,
                    'posterUrl' => $movie?->poster_url,
                    'duration' => $movie?->duration,
                    'genre' => $movie?->genre,
                    'minimumAge' => $movie?->minimum_age,
                    'language' => $movie?->language,
                ],
                'cinema' => [
                    'cinemaId' => $cinema?->cinema_id,
                    'name' => $cinema?->name,
                    'address' => $cinema?->address,
                    'city' => $cinema?->city,
                    'district' => $cinema?->district,
                ],
                'room' => [
                    'roomId' => $room?->room_id,
                    'roomNumber' => $room?->room_number,
                    'roomType' => $room?->room_type,
                ],
            ];
        })->values();

        return $this->respond([
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    // PUBLIC – GET /showtimes/options
    public function searchOptions()
    {
        $formats = Showtime::query()
            ->where('is_active', true)
            ->where('start_time', '>=', now())
            ->whereNotNull('format')
            ->distinct()
            ->orderBy('format')
            ->pluck('format')
            ->values();

        $cities = Cinema::query()
            ->where('is_active', true)
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->values();

        return $this->respond([
            'formats' => $formats,
            'cities' => $cities,
        ]);
    }

    // PUBLIC – GET /showtimes/movie/{movieId}
    public function byMovie(string $movieId)
    {
        $this->findMovieOrFail($movieId);

        $showtimes = Showtime::with(['room', 'movie'])
            ->where('movie_id', $movieId)
            ->where('is_active', true)
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
            ->where('is_active', true)
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

        $cinemaTimezone = config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $startDate = Carbon::parse($data['startDate'], $cinemaTimezone)
            ->startOfDay()
            ->setTimezone('UTC');
        $endDate = Carbon::parse($data['endDate'], $cinemaTimezone)
            ->endOfDay()
            ->setTimezone('UTC');

        $showtimes = Showtime::with(['room', 'movie'])
            ->where('movie_id', $movieId)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->orderBy('start_time')
            ->get();

        return $this->respond(
            ShowtimeResource::collection($showtimes),
            'OK'
        );
    }
}
