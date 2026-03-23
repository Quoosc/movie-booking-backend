# API_SPEC.md — Chi tiết API Endpoints

## Base URL

- **Dev**: `http://localhost:8000/api`
- **Prefix**: Tất cả routes trong `routes/api.php` tự động có prefix `/api`

## Response Format

```json
{
  "code": 200,
  "message": "Success",
  "data": { ... }
}
```

Error response:
```json
{
  "code": 422,
  "message": "Validation Error",
  "errors": { "field": ["error message"] }
}
```

---

## 1. Auth (`/api/auth`)

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| POST | `/auth/register` | ❌ | `AuthController::register` | |
| POST | `/auth/login` | ❌ | `AuthController::login` | |
| POST | `/auth/logout` | ✅ | `AuthController::logout` | |
| POST | `/auth/logout-all?email=` | ✅ | `AuthController::logoutAll` | Xóa tất cả refresh tokens |
| GET | `/auth/refresh` | ✅ | `AuthController::refresh` | Trả accessToken mới |

### Request/Response

```php
// POST /auth/register
Request: { email, username, phoneNumber, password, confirmPassword }
Response: { success: true }

// POST /auth/login
Request: { email, password }
Response: { accessToken, refreshToken, user: { userId, email, role, username, ... } }
```

---

## 2. Movies (`/api/movies`)

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| GET | `/movies` | ❌ | `MovieController::index` | + query: title, genre, status |
| GET | `/movies/search/title?title=` | ❌ | `MovieController::searchByTitle` | |
| GET | `/movies/filter/status?status=` | ❌ | `MovieController::filterByStatus` | SHOWING / UPCOMING |
| GET | `/movies/filter/genre?genre=` | ❌ | `MovieController::filterByGenre` | |
| GET | `/movies/{movieId}` | ❌ | `MovieController::show` | |
| GET | `/movies/{movieId}/showtimes?date=` | ❌ | `MovieController::showtimesByDate` | YYYY-MM-DD |
| POST | `/movies` | ✅ JWT | `MovieController::store` | Admin |
| PUT | `/movies/{movieId}` | ✅ JWT | `MovieController::update` | Admin |
| DELETE | `/movies/{movieId}` | ✅ JWT | `MovieController::destroy` | Admin |

### Resource: `MovieResource`

```php
// MovieResource fields
movieId, title, genre, description, duration, minimumAge,
director, actors, posterUrl, posterCloudinaryId, trailerUrl,
status, language, createdAt, updatedAt
```

---

## 3. Cinemas (`/api/cinemas`)

### Public

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/cinemas` | ❌ | `CinemaController::index` |
| GET | `/cinemas/{cinemaId}` | ❌ | `CinemaController::show` |
| GET | `/cinemas/{cinemaId}/movies?status=` | ❌ | `CinemaController::moviesByCinema` |
| GET | `/cinemas/snacks?cinemaId=` | ❌ | `CinemaController::getSnacks` |
| GET | `/cinemas/{cinemaId}/snacks` | ❌ | `CinemaController::getSnacksByCinema` |

### Admin (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| POST | `/cinemas` | ✅ | `CinemaController::store` |
| PUT | `/cinemas/{cinemaId}` | ✅ | `CinemaController::update` |
| DELETE | `/cinemas/{cinemaId}` | ✅ | `CinemaController::destroy` |

### Rooms (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/cinemas/rooms` | ✅ | `CinemaController::roomsIndex` |
| GET | `/cinemas/rooms/{roomId}` | ✅ | `CinemaController::showRoom` |
| POST | `/cinemas/rooms` | ✅ | `CinemaController::storeRoom` |
| PUT | `/cinemas/rooms/{roomId}` | ✅ | `CinemaController::updateRoom` |
| DELETE | `/cinemas/rooms/{roomId}` | ✅ | `CinemaController::destroyRoom` |

### Snacks Admin (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/cinemas/snacks/all` | ✅ | `CinemaController::getAllSnacks` |
| GET | `/cinemas/snacks/{snackId}` | ✅ | `CinemaController::getSnack` |
| POST | `/cinemas/snacks` | ✅ | `CinemaController::storeSnack` |
| PUT | `/cinemas/snacks/{snackId}` | ✅ | `CinemaController::updateSnack` |
| DELETE | `/cinemas/snacks/{snackId}` | ✅ | `CinemaController::deleteSnack` |

---

## 4. Showtimes (`/api/showtimes`)

### Public

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/showtimes` | ❌ | `ShowtimeController::index` |
| GET | `/showtimes/{showtimeId}` | ❌ | `ShowtimeController::show` |
| GET | `/showtimes/movie/{movieId}` | ❌ | `ShowtimeController::byMovie` |
| GET | `/showtimes/movie/{movieId}/upcoming` | ❌ | `ShowtimeController::upcomingByMovie` |
| GET | `/showtimes/movie/{movieId}/date-range?startDate=&endDate=` | ❌ | `ShowtimeController::byMovieAndDateRange` |
| GET | `/showtimes/room/{roomId}` | ❌ | `ShowtimeController::byRoom` |

### Admin (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| POST | `/showtimes` | ✅ | `ShowtimeController::store` |
| PUT | `/showtimes/{showtimeId}` | ✅ | `ShowtimeController::update` |
| DELETE | `/showtimes/{showtimeId}` | ✅ | `ShowtimeController::destroy` |

### Showtime Ticket Types (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/showtimes/{id}/ticket-types` | ✅ | `ShowtimeTicketTypeController::index` |
| POST | `/showtimes/{id}/ticket-types` | ✅ | `::assignMultiple` |
| POST | `/showtimes/{id}/ticket-types/{ticketTypeId}` | ✅ | `::assignSingle` |
| PUT | `/showtimes/{id}/ticket-types` | ✅ | `::replace` |
| DELETE | `/showtimes/{id}/ticket-types/{ticketTypeId}` | ✅ | `::remove` |

---

## 5. Seats (`/api/seats`)

### Public

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/seats/layout?showtime_id=` | ❌ | `SeatController::layout` |

### Admin (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/seats` | ✅ | `SeatController::index` |
| GET | `/seats/{seatId}` | ✅ | `SeatController::show` |
| POST | `/seats` | ✅ | `SeatController::store` |
| PUT | `/seats/{seatId}` | ✅ | `SeatController::update` |
| DELETE | `/seats/{seatId}` | ✅ | `SeatController::destroy` |
| GET | `/seats/room/{roomId}` | ✅ | `SeatController::getByRoom` |
| POST | `/seats/generate` | ✅ | `SeatController::generate` |
| GET | `/seats/row-labels?rows=` | ✅ | `SeatController::rowLabels` |

### Showtime Seats (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/showtime-seats/{id}` | ✅ | `ShowtimeSeatController::getById` |
| GET | `/showtime-seats/showtime/{showtimeId}` | ✅ | `::getByShowtime` |
| GET | `/showtime-seats/showtime/{id}/available` | ✅ | `::getAvailableByShowtime` |
| PUT | `/showtime-seats/{id}` | ✅ | `::update` |
| PUT | `/showtime-seats/{id}/reset` | ✅ | `::reset` |
| POST | `/showtime-seats/showtime/{id}/recalculate-prices` | ✅ | `::recalculatePrices` |

---

## 6. Ticket Types (`/api/ticket-types`)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/ticket-types?showtimeId=&userId=` | ❌ | `TicketTypeController::index` |
| GET | `/ticket-types/admin` | ✅ | `TicketTypeController::adminIndex` |
| POST | `/ticket-types` | ✅ | `TicketTypeController::store` |
| PUT | `/ticket-types/{id}` | ✅ | `TicketTypeController::update` |
| DELETE | `/ticket-types/{id}` | ✅ | `TicketTypeController::destroy` |

---

## 7. Promotions (`/api/promotions`)

### Public

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/promotions?filter=` | ❌ | `PromotionController::index` |
| GET | `/promotions/active` | ❌ | `PromotionController::getActive` |
| GET | `/promotions/valid` | ❌ | `PromotionController::getValid` |
| GET | `/promotions/code/{code}` | ❌ | `PromotionController::showByCode` |
| GET | `/promotions/{promotionId}` | ❌ | `PromotionController::show` |

### Admin (auth.jwt)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| POST | `/promotions` | ✅ | `PromotionController::store` |
| PUT | `/promotions/{id}` | ✅ | `PromotionController::update` |
| PATCH | `/promotions/{id}/deactivate` | ✅ | `PromotionController::deactivate` |
| DELETE | `/promotions/{id}` | ✅ | `PromotionController::destroy` |

---

## 8. Seat Locks (`/api/seat-locks`)

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| POST | `/seat-locks` | Guest/JWT | `SeatLockController::lockSeats` | X-Session-Id cho guest |
| GET | `/seat-locks/availability/{showtimeId}` | Guest/JWT | `::checkAvailability` | |
| DELETE | `/seat-locks/showtime/{showtimeId}` | Guest/JWT | `::releaseSeats` | |

### Form Request: `LockSeatsRequest`

```php
[
  'showtimeId' => 'required|uuid',
  'seats' => 'required|array|min:1',
  'seats.*.showtimeSeatId' => 'required|uuid',
  'seats.*.ticketTypeId' => 'required|uuid',
]
```

---

## 9. Bookings (`/api/bookings`)

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| POST | `/bookings/price-preview` | Guest/JWT | `BookingController::pricePreview` | |
| POST | `/bookings/confirm` | Guest/JWT | `BookingController::confirmBooking` | |
| GET | `/bookings/my-bookings` | ✅ JWT | `BookingController::getUserBookings` | |
| GET | `/bookings/{bookingId}` | Optional | `BookingController::showBookingPublic` | auth.optional |
| PATCH | `/bookings/{bookingId}/qr` | ✅ JWT | `BookingController::updateQrCode` | |

### Form Requests

```php
// PricePreviewRequest
[
  'lockId' => 'required|uuid',
  'snacks' => 'nullable|array',
  'snacks.*.snackId' => 'required|uuid',
  'snacks.*.quantity' => 'required|integer|min:1',
  'promotionCode' => 'nullable|string',
]

// ConfirmBookingRequest
[
  'lockId' => 'required|uuid',
  'promotionCode' => 'nullable|string',
  'snackCombos' => 'nullable|array',
  'snackCombos.*.snackId' => 'required|uuid',
  'snackCombos.*.quantity' => 'required|integer|min:1',
  'guestInfo' => 'nullable|array',
  'guestInfo.email' => 'required_with:guestInfo|email',
  'guestInfo.username' => 'required_with:guestInfo|string',
  'guestInfo.phoneNumber' => 'required_with:guestInfo|string',
]
```

---

## 10. Checkout (`/api/checkout`)

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| POST | `/checkout` | Guest/JWT | `CheckoutController::confirmAndInitiate` | Confirm + payment 1 bước |

---

## 11. Payments (`/api/payments`)

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| POST | `/payments/order` | Guest/JWT | `PaymentController::initiatePayment` | Public |
| POST | `/payments/order/capture` | Guest/JWT | `PaymentController::capturePayment` | Public |
| GET | `/payments/momo/ipn` | ❌ | `PaymentController::handleMomoIpnGet` | Momo callback |
| POST | `/payments/momo/ipn` | ❌ | `PaymentController::handleMomoIpnPost` | Momo IPN |
| GET | `/payments/search?...` | ✅ JWT | `PaymentController::searchPayments` | query params |
| POST | `/payments/{paymentId}/refund` | ✅ Admin | `RefundController::refund` | role:admin |

### Form Requests

```php
// InitiatePaymentRequest
[
  'bookingId' => 'required|uuid',
  'paymentMethod' => 'required|in:PAYPAL,MOMO',
  'amount' => 'required|numeric|min:1',
]

// ConfirmPaymentRequest
[
  'transactionId' => 'required|string',
  'paymentMethod' => 'required|in:PAYPAL,MOMO',
]

// PaymentSearchRequest
[
  'bookingId' => 'nullable|uuid',
  'userId' => 'nullable|uuid',
  'status' => 'nullable|in:PENDING,COMPLETED,FAILED,REFUNDED,CANCELLED',
  'method' => 'nullable|in:PAYPAL,MOMO',
  'startDate' => 'nullable|date',
  'endDate' => 'nullable|date',
]
```

---

## 12. Users (`/api/users`) — auth.jwt

| Method | Endpoint | Auth | Controller Method | Ghi chú |
|--------|----------|------|-------------------|---------|
| GET | `/users/profile` | ✅ | `UsersController::getProfile` | |
| PUT | `/users/profile` | ✅ | `UsersController::updateProfile` | |
| PATCH | `/users/password` | ✅ | `UsersController::updatePassword` | |
| GET | `/users/loyalty` | ✅ | `UsersController::getLoyalty` | |
| GET | `/users` | ✅ | `UsersController::listAllUsers` | Admin |
| GET | `/users/{userId}` | ✅ | `UsersController::getUserById` | Admin |
| PATCH | `/users/{userId}/role` | ✅ | `UsersController::updateUserRole` | Admin, body: text/plain |
| DELETE | `/users/{userId}` | ✅ | `UsersController::deleteUser` | Admin |

---

## 13. Membership Tiers (`/api/membership-tiers`) — auth.jwt

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/membership-tiers` | ✅ | `MembershipTierController::index` |
| GET | `/membership-tiers/active` | ✅ | `::getActive` |
| GET | `/membership-tiers/name/{name}` | ✅ | `::getByName` |
| GET | `/membership-tiers/{id}` | ✅ | `::show` |
| POST | `/membership-tiers` | ✅ | `::store` |
| PUT | `/membership-tiers/{id}` | ✅ | `::update` |
| PATCH | `/membership-tiers/{id}/deactivate` | ✅ | `::deactivate` |
| DELETE | `/membership-tiers/{id}` | ✅ | `::destroy` |

---

## 14. Pricing — auth.jwt

### Price Base (`/api/price-base`)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/price-base` | ✅ | `PriceBaseController::index` |
| GET | `/price-base/active` | ✅ | `::getActive` |
| GET | `/price-base/{id}` | ✅ | `::show` |
| POST | `/price-base` | ✅ | `::store` |
| PUT | `/price-base/{id}` | ✅ | `::update` |
| DELETE | `/price-base/{id}` | ✅ | `::destroy` |

### Price Modifiers (`/api/price-modifiers`)

| Method | Endpoint | Auth | Controller Method |
|--------|----------|------|-------------------|
| GET | `/price-modifiers` | ✅ | `PriceModifierController::index` |
| GET | `/price-modifiers/active` | ✅ | `::getActive` |
| GET | `/price-modifiers/by-condition?conditionType=` | ✅ | `::getByCondition` |
| GET | `/price-modifiers/{id}` | ✅ | `::show` |
| POST | `/price-modifiers` | ✅ | `::store` |
| PUT | `/price-modifiers/{id}` | ✅ | `::update` |
| DELETE | `/price-modifiers/{id}` | ✅ | `::destroy` |

---

## Legend

| Icon | Nghĩa |
|------|-------|
| ❌ | Public endpoint (không cần auth) |
| ✅ JWT | Cần `Authorization: Bearer <token>` |
| ✅ Admin | Cần JWT + role ADMIN (`auth.jwt` + `role:admin`) |
| Guest/JWT | Chấp nhận `X-Session-Id` header (guest) hoặc JWT (member) |
| Optional | `auth.optional` — JWT nếu có, guest vẫn cho phép |
