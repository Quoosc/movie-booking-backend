<?php
// app/Http/Controllers/CinemaController.php
namespace App\Http\Controllers;

use App\Http\Resources\CinemaResource;
use App\Http\Resources\MovieResource;
use App\Http\Resources\RoomResource;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\SnackResource;
use App\Services\CinemaService;


class CinemaController extends Controller
{
    private CinemaService $cinemaService;

    public function __construct(CinemaService $cinemaService)
    {
        $this->cinemaService = $cinemaService;
    }

    // ======= COMMON RESPONSE (giống AuthController, MovieController) =======
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

    // ========== PUBLIC: GET /cinemas ==========
    public function index()
    {
        $cinemas = Cinema::orderBy('name')->get();

        return $this->respond(CinemaResource::collection($cinemas));
    }

    // ========== PUBLIC: GET /cinemas/{cinemaId} ==========
    public function show(string $cinemaId)
    {
        $cinema = Cinema::findOrFail($cinemaId);

        return $this->respond(new CinemaResource($cinema));
    }

    // ========== PUBLIC: GET /cinemas/{cinemaId}/movies?status=SHOWING ==========
    public function moviesByCinema(Request $request, string $cinemaId)
    {
        $cinema = Cinema::findOrFail($cinemaId);

        $status = $request->query('status');

        $query = Movie::query()
            ->select('movies.*')
            ->distinct()
            ->join('showtimes', 'showtimes.movie_id', '=', 'movies.movie_id')
            ->join('rooms', 'rooms.room_id', '=', 'showtimes.room_id')
            ->where('rooms.cinema_id', $cinema->cinema_id);

        if ($status) {
            $query->where('movies.status', $status);
        }

        $movies = $query->get();

        return $this->respond(MovieResource::collection($movies));
    }

    // ===== ADMIN: POST /cinemas =====
    public function store(Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'hotline' => 'required|string|max:30',
        ]);

        $cinema = new Cinema();
        $cinema->cinema_id = (string) Str::uuid();
        $cinema->name      = $data['name'];
        $cinema->address   = $data['address'];
        $cinema->hotline   = $data['hotline'];
        $cinema->status    = 'ACTIVE'; // optional, nếu có cột status
        $cinema->save();

        return $this->respond([
            'cinemaId' => $cinema->cinema_id,
            'name'     => $cinema->name,
            'address'  => $cinema->address,
            'hotline'  => $cinema->hotline,
        ], 'OK', Response::HTTP_CREATED);
    }

    // ========== ADMIN: PUT /cinemas/{cinemaId} ==========
    public function update(Request $request, string $cinemaId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $cinema = Cinema::findOrFail($cinemaId);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'address'     => 'sometimes|required|string|max:255',
            'city'        => 'nullable|string|max:100',
            'phoneNumber' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'isActive'    => 'boolean',
        ]);

        if (array_key_exists('name', $data))        $cinema->name         = $data['name'];
        if (array_key_exists('address', $data))     $cinema->address      = $data['address'];
        if (array_key_exists('city', $data))        $cinema->city         = $data['city'];
        if (array_key_exists('phoneNumber', $data)) $cinema->phone_number = $data['phoneNumber'];
        if (array_key_exists('description', $data)) $cinema->description  = $data['description'];
        if (array_key_exists('isActive', $data))    $cinema->is_active    = $data['isActive'];

        $cinema->save();

        return $this->respond(new CinemaResource($cinema), 'Cinema updated');
    }

    // ========== ADMIN: DELETE /cinemas/{cinemaId} ==========
    public function destroy(string $cinemaId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $cinema = Cinema::withCount('rooms')->findOrFail($cinemaId);

        if ($cinema->rooms_count > 0) {
            return $this->respond(
                null,
                'Cannot delete cinema with existing rooms',
                Response::HTTP_CONFLICT
            );
        }

        $cinema->delete();

        return $this->respond(null, 'Cinema deleted');
    }

    // ========== ADMIN: GET /cinemas/rooms?cinemaId=... ==========
    public function roomsIndex(Request $request)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $query = Room::with('cinema')->orderBy('room_number');

        if ($cinemaId = $request->query('cinemaId')) {
            $query->where('cinema_id', $cinemaId);
        }

        $rooms = $query->get();

        return $this->respond(RoomResource::collection($rooms));
    }

    public function showRoom(string $roomId)
    {
        // dùng primary key room_id (model Room đã set $primaryKey = 'room_id')
        $room = Room::findOrFail($roomId);

        // nếu bạn đang dùng pattern respond() như MovieController:
        // return $this->respond(new RoomResource($room));

        // còn không thì trả đơn giản như các Resource khác:
        return new RoomResource($room);
    }


    // ========== ADMIN: POST /cinemas/rooms ==========
    public function storeRoom(Request $request)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $data = $request->validate([
            'cinemaId'   => 'required|exists:cinemas,cinema_id',
            'roomNumber' => 'required|integer|min:1',
            'roomType'   => 'nullable|string|max:50',
            'isActive'   => 'boolean',
        ]);

        $room = new Room();
        $room->cinema_id   = $data['cinemaId'];
        $room->room_number = $data['roomNumber'];
        $room->room_type   = $data['roomType'] ?? 'STANDARD';
        $room->is_active   = $data['isActive'] ?? true;
        $room->save();

        $room->load('cinema');

        return $this->respond(new RoomResource($room), 'Room created', Response::HTTP_CREATED);
    }

    // ========== ADMIN: PUT /cinemas/rooms/{roomId} ==========
    public function updateRoom(Request $request, string $roomId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $room = Room::findOrFail($roomId);

        $data = $request->validate([
            'cinemaId'   => 'sometimes|required|exists:cinemas,cinema_id',
            'roomNumber' => 'sometimes|required|integer|min:1',
            'roomType'   => 'nullable|string|max:50',
            'isActive'   => 'boolean',
        ]);

        if (array_key_exists('cinemaId', $data))   $room->cinema_id   = $data['cinemaId'];
        if (array_key_exists('roomNumber', $data)) $room->room_number = $data['roomNumber'];
        if (array_key_exists('roomType', $data))   $room->room_type   = $data['roomType'];
        if (array_key_exists('isActive', $data))   $room->is_active   = $data['isActive'];

        $room->save();
        $room->load('cinema');

        return $this->respond(new RoomResource($room), 'Room updated');
    }

    // ========== ADMIN: DELETE /cinemas/rooms/{roomId} ==========
    public function destroyRoom(string $roomId)
    {
        if ($resp = $this->ensureAdmin()) return $resp;

        $room = Room::withCount('showtimes')->findOrFail($roomId);

        if ($room->showtimes_count > 0) {
            return $this->respond(
                null,
                'Cannot delete room with existing showtimes',
                Response::HTTP_CONFLICT
            );
        }

        $room->delete();

        return $this->respond(null, 'Room deleted');
    }


    // =============== SNACKS ===============

    // POST /api/cinemas/snacks
    public function storeSnack(Request $request)
    {
        $data = $request->validate([
            'cinemaId'          => 'required|uuid|exists:cinemas,cinema_id',
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'price'             => 'required|numeric|min:0',
            'type'              => 'required|string|max:255',
            'imageUrl'          => 'nullable|string',
            'imageCloudinaryId' => 'nullable|string',
        ]);

        $snack = $this->cinemaService->addSnack($data);

        return (new SnackResource($snack))
            ->response()
            ->setStatusCode(201);
    }

    // PUT /api/cinemas/snacks/{snackId}
    public function updateSnack(string $snackId, Request $request)
    {
        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'description'       => 'sometimes|nullable|string',
            'price'             => 'sometimes|numeric|min:0',
            'type'              => 'sometimes|string|max:255',
            'imageUrl'          => 'sometimes|nullable|string',
            'imageCloudinaryId' => 'sometimes|nullable|string',
        ]);

        $snack = $this->cinemaService->updateSnack($snackId, $data);

        return new SnackResource($snack);
    }

    // DELETE /api/cinemas/snacks/{snackId}
    public function deleteSnack(string $snackId)
    {
        $this->cinemaService->deleteSnack($snackId);

        return response()->json(null, 200);
    }

    // GET /api/cinemas/snacks/{snackId}
    public function getSnack(string $snackId)
    {
        $snack = $this->cinemaService->getSnack($snackId);

        return new SnackResource($snack);
    }

    // GET /api/cinemas/snacks  (admin list tất cả snack)
    public function getAllSnacks()
    {
        $snacks = $this->cinemaService->getAllSnacks();

        return SnackResource::collection($snacks);
    }

    // GET /api/cinemas/{cinemaId}/snacks  (public dùng cho booking)
    public function getSnacksByCinema(string $cinemaId)
    {
        $snacks = $this->cinemaService->getSnacksByCinema($cinemaId);

        return SnackResource::collection($snacks);
    }
}
