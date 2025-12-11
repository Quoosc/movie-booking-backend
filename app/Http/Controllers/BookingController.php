<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use App\Services\BookingService;
// use App\Support\SessionHelper;
// use App\DTO\Bookings\LockSeatsRequest;
// use App\DTO\Bookings\LockSeatsResponse;
// class BookingController extends Controller
// {
//     protected function respond($data = null, string $message = 'OK', int $code = 200)
//     {
//         return response()->json([
//             'code'    => $code,
//             'message' => $message,
//             'data'    => $data,
//         ], $code);
//     }

//     public function __construct(
//         protected BookingService $bookingService,
//         protected SessionHelper  $sessionHelper,
//     ) {}

//     /**
//      * GET /api/bookings/my-bookings
//      * Lịch sử đặt vé của user hiện tại
//      */
//     public function myBookings(Request $request)
//     {
//         $user = $request->user(); // lấy current user từ middleware JWT

//         $rows = DB::table('bookings as b')
//             ->join('showtimes as st', 'b.showtime_id', '=', 'st.showtime_id')
//             ->join('movies as m', 'st.movie_id', '=', 'm.movie_id')
//             ->where('b.user_id', $user->user_id)
//             ->orderByDesc('b.booked_at')
//             ->select(
//                 'b.booking_id',
//                 'm.title as movie_title',
//                 'b.finalPrice as final_price',
//                 'b.total_price',
//                 'b.status',
//                 'b.booked_at'
//             )
//             ->get()
//             ->map(function ($row) {
//                 $total = $row->final_price ?? $row->total_price;

//                 return [
//                     'bookingId'  => $row->booking_id,
//                     'movieTitle' => $row->movie_title,
//                     'totalPrice' => $total ? (float) $total : 0,
//                     'status'     => $row->status,
//                     'bookedAt'   => $row->booked_at,
//                 ];
//             });

//         return $this->respond($rows);
//     }

//     /**
//      * GET /api/bookings/{bookingId}
//      * Chi tiết 1 booking (phim, ghế, qrCode,...)
//      */
//     public function show(Request $request, string $bookingId)
//     {
//         $user = $request->user();

//         // Lấy thông tin booking + movie + cinema + room + showtime
//         $booking = DB::table('bookings as b')
//             ->join('showtimes as st', 'b.showtime_id', '=', 'st.showtime_id')
//             ->join('movies as m', 'st.movie_id', '=', 'm.movie_id')
//             ->join('rooms as r', 'st.room_id', '=', 'r.room_id')
//             ->join('cinemas as c', 'r.cinema_id', '=', 'c.cinema_id')
//             ->where('b.booking_id', $bookingId)
//             ->where('b.user_id', $user->user_id) // user chỉ xem được vé của mình
//             ->select(
//                 'b.booking_id',
//                 'b.booked_at',
//                 'b.total_price',
//                 'b.finalPrice as final_price',
//                 'b.status',
//                 'b.qr_code',
//                 'b.qr_payload',
//                 'm.movie_id',
//                 'm.title as movie_title',
//                 'st.showtime_id',
//                 'st.start_time',
//                 'c.cinema_id',
//                 'c.name as cinema_name',
//                 'r.room_id',
//                 'r.room_number'
//             )
//             ->first();

//         if (!$booking) {
//             return $this->respond(null, 'Booking not found', 404);
//         }

//         $total = $booking->final_price ?? $booking->total_price;

//         // Danh sách ghế
//         $seats = DB::table('booking_seats as bs')
//             ->join('showtime_seats as sts', 'bs.showtime_seat_id', '=', 'sts.showtime_seat_id')
//             ->join('seats as s', 'sts.seat_id', '=', 's.seat_id')
//             ->where('bs.booking_id', $bookingId)
//             ->select(
//                 's.row_label',
//                 's.seat_number',
//                 'bs.price'
//             )
//             ->get()
//             ->map(function ($row) {
//                 return [
//                     'rowLabel'   => $row->row_label,
//                     'seatNumber' => $row->seat_number,
//                     'price'      => (float) $row->price,
//                 ];
//             });

//         // Danh sách bắp nước
//         $snacks = DB::table('booking_snacks as bs')
//             ->join('snacks as s', 'bs.snack_id', '=', 's.snack_id')
//             ->where('bs.booking_id', $bookingId)
//             ->select(
//                 's.snack_id',
//                 's.name',
//                 's.type',
//                 's.price',
//                 'bs.quantity'
//             )
//             ->get()
//             ->map(function ($row) {
//                 return [
//                     'snackId'  => $row->snack_id,
//                     'name'     => $row->name,
//                     'type'     => $row->type,
//                     'price'    => (float) $row->price,
//                     'quantity' => (int) $row->quantity,
//                 ];
//             });

//         $response = [
//             'bookingId'  => $booking->booking_id,
//             'status'     => $booking->status,
//             'bookedAt'   => $booking->booked_at,
//             'totalPrice' => $total ? (float) $total : 0,
//             'qrCode'     => $booking->qr_code,
//             'qrPayload'  => $booking->qr_payload,

//             'movie' => [
//                 'movieId'    => $booking->movie_id,
//                 'title'      => $booking->movie_title,
//             ],

//             'cinema' => [
//                 'cinemaId'   => $booking->cinema_id,
//                 'name'       => $booking->cinema_name,
//             ],

//             'showtime' => [
//                 'showtimeId' => $booking->showtime_id,
//                 'startTime'  => $booking->start_time,
//                 'roomNumber' => $booking->room_number,
//             ],

//             'seats'  => $seats,
//             'snacks' => $snacks,
//         ];

//         return $this->respond($response);
//     }

//     public function lockSeats(Request $request)
//     {
//         $validated = $request->validate([
//             'showtimeId'            => 'required|uuid',
//             'seats'                 => 'required|array|min:1',
//             'seats.*.showtimeSeatId' => 'required|uuid',
//             'seats.*.ticketTypeId'  => 'nullable|uuid',
//             'snacks'                => 'array',
//             'snacks.*.snackId'      => 'required_with:snacks|uuid',
//             'snacks.*.quantity'     => 'required_with:snacks|integer|min:1',
//             'promotionCode'         => 'nullable|string',
//         ]);

//         $sessionContext = $this->sessionHelper->extractSessionContext($request);

//         $dto = new LockSeatsRequest(
//             showtimeId: $validated['showtimeId'],
//             seats: $validated['seats'],
//             snacks: $validated['snacks'] ?? [],
//             promotionCode: $validated['promotionCode'] ?? null,
//         );

//         $resp = $this->bookingService->lockSeats($dto, $sessionContext);

//         return response()->json([
//             'code'    => 200,
//             'message' => 'Seats locked successfully',
//             'data'    => [
//                 'lockToken'    => $resp->lockToken,
//                 'showtimeId'   => $resp->showtimeId,
//                 'remainSeconds' => $resp->remainSeconds,
//                 'expiresAt'    => $resp->expiresAtIso,
//                 'seats'        => $resp->seatItems,
//                 'snacks'       => $resp->snackItems,
//                 'priceSummary' => $resp->priceSummary,
//             ],
//         ]);
//     }
// }



namespace App\Http\Controllers;

use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // POST /api/bookings/price-preview
    public function pricePreview(Request $request)
    {
        $payload = $request->validate([
            'lockKey'       => 'required|string',
            'promotionCode' => 'nullable|string',
            'snacks'        => 'array',
            'snacks.*.snackId'  => 'required_with:snacks|string|exists:snacks,snack_id',
            'snacks.*.quantity' => 'required_with:snacks|integer|min:1',
        ]);

        $user = Auth::user();

        $result = $this->bookingService->calculatePricePreview($payload, $user);

        return $this->respond($result);
    }

    // POST /api/bookings/confirm
    public function confirmBooking(Request $request)
    {
        $data = $request->validate([
            'lockKey'       => 'required|string',
            'customerName'  => 'required|string|max:255',
            'customerEmail' => 'required|email|max:255',
            'customerPhone' => 'required|string|max:30',
        ]);

        $user = Auth::user();

        $result = $this->bookingService->confirmBooking($data, $user);

        return $this->respond($result, 'Booking confirmed', Response::HTTP_CREATED);
    }

    // GET /api/bookings/my-bookings
    public function getUserBookings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->respond(null, 'Unauthenticated', Response::HTTP_UNAUTHORIZED);
        }

        $result = $this->bookingService->getUserBookings($user->user_id);

        return $this->respond($result);
    }

    // GET /api/bookings/{bookingId}
    public function getBookingById(string $bookingId, Request $request)
    {
        $user = $request->user(); // có thể null nếu admin
        $result = $this->bookingService->getBookingByIdForUser($bookingId, $user?->user_id);

        return $this->respond($result);
    }

    // PATCH /api/bookings/{bookingId}/qr
    public function updateQrCode(string $bookingId, Request $request)
    {
        $data = $request->validate([
            'qrPayload' => 'required|string',
        ]);

        $result = $this->bookingService->updateQrCode($bookingId, $data['qrPayload']);

        return $this->respond($result, 'QR code updated');
    }
}
