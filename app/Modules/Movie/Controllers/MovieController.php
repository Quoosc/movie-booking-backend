<?php

namespace App\Modules\Movie\Controllers;

use App\Core\Http\Controllers\BaseController;

use App\Modules\Movie\Resources\MovieResource;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;


use Illuminate\Support\Facades\DB;


class MovieController extends BaseController
{
    // ======== COMMON RESPONSE HELPER (giống AuthController) ========
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
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user || $user->role !== 'ADMIN') {
            return $this->respond(null, 'Admin access required', Response::HTTP_FORBIDDEN);
        }

        return null;
    }


    // ========= ADMIN: POST /movies =========
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255', Rule::unique('movies', 'title')],
            'genre'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'duration'    => 'required|integer|min:1',
            'minimumAge'  => 'nullable|integer|min:0',
            'director'    => 'nullable|string|max:255',
            'actors'      => 'nullable|string',
            'posterUrl'   => 'nullable|url',
            'posterCloudinaryId' => 'nullable|string|max:255',
            'trailerUrl'  => 'nullable|url',
            'status'        => 'required|in:SHOWING,UPCOMING',
            'language'      => 'nullable|string|max:50',
            'premiereDate'  => 'nullable|date',
        ]);

        $movie = Movie::create([
            'title'                => $validated['title'],
            'genre'                => $validated['genre'] ?? null,
            'description'          => $validated['description'] ?? null,
            'duration'             => $validated['duration'],
            'minimum_age'          => $validated['minimumAge'] ?? null,
            'director'             => $validated['director'] ?? null,
            'actors'               => $validated['actors'] ?? null,
            'poster_url'           => $validated['posterUrl'] ?? null,
            'poster_cloudinary_id' => $validated['posterCloudinaryId'] ?? null,
            'trailer_url'          => $validated['trailerUrl'] ?? null,
            'status'               => $validated['status'],
            'language'             => $validated['language'] ?? null,
            'premiere_date'        => $validated['premiereDate'] ?? null,
        ]);

        return MovieResource::make($movie);
    }

    // ========= ADMIN: PUT /movies/{movieId} =========
    public function update(string $movieId, Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $movie = Movie::findOrFail($movieId);

        $data = $request->validate([
            'title'              => ['sometimes', 'string', 'max:255', Rule::unique('movies', 'title')->ignore($movie->getKey(), $movie->getKeyName())],
            'genre'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'        => ['sometimes', 'nullable', 'string'],
            'duration'           => ['sometimes', 'integer', 'min:1'],
            'minimumAge'         => ['sometimes', 'nullable', 'integer', 'min:0'],
            'director'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'actors'             => ['sometimes', 'nullable', 'string'],
            'posterUrl'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'posterCloudinaryId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trailerUrl'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'status'             => ['sometimes', Rule::in(['SHOWING', 'UPCOMING'])],
            'language'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'premiereDate'       => ['sometimes', 'nullable', 'date'],
        ]);

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'minimumAge':
                    $movie->minimum_age = $value;
                    break;
                case 'posterUrl':
                    $movie->poster_url = $value;
                    break;
                case 'posterCloudinaryId':
                    $movie->poster_cloudinary_id = $value;
                    break;
                case 'trailerUrl':
                    $movie->trailer_url = $value;
                    break;
                case 'premiereDate':
                    $movie->premiere_date = $value;
                    break;
                default:
                    $movie->{$key} = $value;
            }
        }

        $movie->save();

        return $this->respond(new MovieResource($movie));
    }

    // ========= ADMIN: DELETE /movies/{movieId} =========
    public function destroy(string $movieId)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $movie = Movie::with('showtimes')->findOrFail($movieId);

        if ($movie->showtimes->isNotEmpty()) {
            return $this->respond(
                null,
                'Cannot delete movie with existing showtimes',
                Response::HTTP_BAD_REQUEST
            );
        }

        $movie->delete();

        return $this->respond(null, 'OK', Response::HTTP_OK);
    }

    // ========= PUBLIC: GET /movies/{movieId} =========
    public function show(string $movieId)
    {
        $movie = Movie::withAvg('reviews', 'rating')->withCount('reviews')->findOrFail($movieId);

        return $this->respond(new MovieResource($movie));
    }

    // ========= PUBLIC: GET /movies/{movieId}/related =========
    public function related(string $movieId)
    {
        $movie = Movie::findOrFail($movieId);
        $genres = collect(preg_split('/[,;|]/', (string) $movie->genre))
            ->map(fn ($genre) => trim($genre))
            ->filter()
            ->values();

        $query = Movie::query()
            ->where('movie_id', '!=', $movie->movie_id)
            ->whereIn('status', ['SHOWING', 'UPCOMING']);

        if ($genres->isNotEmpty()) {
            $query->where(function (Builder $builder) use ($genres) {
                foreach ($genres as $genre) {
                    $builder->orWhere('genre', 'LIKE', '%' . $genre . '%');
                }
            });
        }

        $related = $query
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByDesc('reviews_count')
            ->orderByRaw('COALESCE(premiere_date, created_at) DESC')
            ->limit(8)
            ->get();

        if ($related->isEmpty()) {
            $related = Movie::query()
                ->where('movie_id', '!=', $movie->movie_id)
                ->whereIn('status', ['SHOWING', 'UPCOMING'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->orderByRaw('COALESCE(premiere_date, created_at) DESC')
                ->limit(8)
                ->get();
        }

        return $this->respond(MovieResource::collection($related));
    }

    // ========= PUBLIC: GET /movies =========
    public function index(Request $request)
    {
        $title  = $request->query('title');
        $genre  = $request->query('genre');
        $status = $request->query('status');

        /** @var Builder $query */
        $query = Movie::query();

        if ($title) {
            $query->where('title', 'LIKE', '%' . $title . '%');
        }

        if ($genre) {
            $query->where('genre', 'LIKE', '%' . $genre . '%');
        }

        if ($status) {
            $query->where('status', $status);
        }

        $movies = $query->withAvg('reviews', 'rating')->withCount('reviews')
            ->orderByRaw('COALESCE(premiere_date, created_at) DESC')
            ->get();

        return $this->respond(MovieResource::collection($movies));
    }




    // ========= PUBLIC: GET /movies/search/title?title= =========
    public function searchByTitle(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string'],
        ]);

        $title  = $request->query('title');

        $movies = Movie::withAvg('reviews', 'rating')->withCount('reviews')->where('title', 'LIKE', '%' . $title . '%')->get();

        return $this->respond(MovieResource::collection($movies));
    }

//   public function searchByTitle(Request $request)
// {
//     $title = $request->query('title'); 

//     $query = "SELECT * FROM movies WHERE title LIKE '%$title%'";
//     $movies = DB::select($query); 

//     return $this->respond(MovieResource::collection($movies));
// }

    // ========= PUBLIC: GET /movies/filter/status?status=SHOWING =========
    public function filterByStatus(Request $request)
    {
        $request->validate([
            'status' => ['required', Rule::in(['SHOWING', 'UPCOMING'])],
        ]);

        $movies = Movie::withAvg('reviews', 'rating')->withCount('reviews')
            ->where('status', $request->query('status'))
            ->orderByRaw('COALESCE(premiere_date, created_at) DESC')
            ->get();

        return $this->respond(MovieResource::collection($movies));
    }

    // ========= PUBLIC: GET /movies/filter/genre?genre= =========
    public function filterByGenre(Request $request)
    {
        $request->validate([
            'genre' => ['required', 'string'],
        ]);

        $genre = $request->query('genre');

        $movies = Movie::withAvg('reviews', 'rating')->withCount('reviews')->where('genre', 'LIKE', '%' . $genre . '%')->get();

        return $this->respond(MovieResource::collection($movies));
    }

    // ========= PUBLIC: GET /movies/{movieId}/showtimes?date=YYYY-MM-DD =========
    public function showtimesByDate(string $movieId, Request $request)
    {
        $movie = Movie::findOrFail($movieId);

        $dateStr = $request->query('date');
        $date = $dateStr ? Carbon::parse($dateStr)->startOfDay() : Carbon::today();

        $today = Carbon::today();
        $isToday = $date->isSameDay($today);

        // start / end trong ngày
        $startOfDay = $isToday ? Carbon::now() : $date->copy()->startOfDay();
        $endOfDay   = $date->copy()->endOfDay();

        $showtimes = Showtime::with(['room.cinema'])
            ->where('movie_id', $movie->movie_id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startOfDay, $endOfDay])
            ->orderBy('start_time')
            ->get();

        // Group theo cinema
        $cinemas = [];

        foreach ($showtimes as $st) {
            $cinema = $st->room->cinema;
            $cinemaId = (string) $cinema->cinema_id;

            if (!isset($cinemas[$cinemaId])) {
                $cinemas[$cinemaId] = [
                    'cinemaId'   => $cinemaId,
                    'cinemaName' => $cinema->name,
                    'address'    => $cinema->address,
                    'showtimes'  => [],
                ];
            }

            $cinemas[$cinemaId]['showtimes'][] = [
                'showtimeId' => (string) $st->showtime_id,
                'startTime'  => $st->start_time instanceof Carbon
                    ? $st->start_time->toIso8601String()
                    : (string) $st->start_time,
                'format'     => $st->format,
                'roomName'   => 'Phòng ' . $st->room->room_number,
            ];
        }

        // trả về mảng CinemaShowtimesResponse[]
        $cinemaList = array_values($cinemas);

        return $this->respond($cinemaList);
    }
}
