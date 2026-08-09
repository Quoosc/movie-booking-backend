# API Specification

> Cập nhật từ `php artisan route:list --json` và controller/request ngày 2026-08-09.

## 1. Tổng quan

- Local base URL: `http://localhost:8000/api`.
- Docker compose publish backend tại `http://localhost:8080/api`.
- Route snapshot: 152 tổng, 146 dưới `/api`.
- 95 API routes dùng `auth.jwt`, 75 dùng `role:admin`, 7 dùng `auth.optional`.
- Google OAuth dùng web routes, không có prefix `/api`.

## 2. Response và access

Response phổ biến:

```json
{
  "code": 200,
  "message": "OK",
  "data": {}
}
```

Một số controller booking/payment trả object/array trực tiếp. Client phải coi HTTP status là nguồn chính và chỉ unwrap `data` khi field tồn tại.

| Access | Yêu cầu |
|---|---|
| Public | Không có auth middleware |
| JWT | `Authorization: Bearer <accessToken>` |
| Optional | `auth.optional`; user được resolve nếu token hợp lệ |
| Admin | `auth.jwt` + `role:admin` |
| Guest session | `X-Session-Id` cho lock/preview/confirm |
| Guest booking | `X-Booking-Access-Token` cho detail/payment của guest |

## 3. Route inventory theo domain

### Auth — 5 routes

| Method | URI | Access | Ghi chú |
|---|---|---|---|
| `POST` | `/auth/register` | Public, `5/min` | Đăng ký |
| `POST` | `/auth/login` | Public, `10/min` | Access + refresh token |
| `POST` | `/auth/logout` | Refresh token/cookie | Thu hồi phiên hiện tại |
| `POST` | `/auth/logout-all` | JWT | Thu hồi mọi phiên của current user |
| `GET/POST` | `/auth/refresh` | Refresh token/cookie, `30/min` | Rotate refresh token |

Payload chính:

```json
// register
{ "email": "...", "username": "...", "phoneNumber": "...", "password": "...", "confirmPassword": "..." }

// login
{ "email": "...", "password": "..." }
```

### Users — 9 routes

| Method | URI | Access |
|---|---|---|
| `GET/PUT` | `/users/profile` | JWT |
| `PATCH` | `/users/password` | JWT |
| `GET` | `/users/loyalty` | JWT |
| `GET` | `/users/loyalty/transactions` | JWT |
| `GET` | `/users` | Admin |
| `GET` | `/users/{userId}` | Admin |
| `PATCH` | `/users/{userId}/role` | Admin; body text role |
| `DELETE` | `/users/{userId}` | Admin |

### Membership tiers — 8 routes

Mọi GET hiện nằm trong `auth.jwt`; mutation thêm `role:admin`.

| Method | URI | Access |
|---|---|---|
| `GET` | `/membership-tiers`, `/active`, `/name/{name}`, `/{id}` | JWT |
| `POST` | `/membership-tiers` | Admin |
| `PUT` | `/membership-tiers/{id}` | Admin |
| `PATCH` | `/membership-tiers/{id}/deactivate` | Admin |
| `DELETE` | `/membership-tiers/{id}` | Admin |

### Movies và reviews — 19 routes

| Method | URI | Access |
|---|---|---|
| `GET` | `/movies` | Public; query `title`, `genre`, `status` |
| `GET` | `/movies/search/title?title=` | Public |
| `GET` | `/movies/filter/status?status=` | Public |
| `GET` | `/movies/filter/genre?genre=` | Public |
| `GET` | `/movies/{movieId}` | Public |
| `GET` | `/movies/{movieId}/related` | Public |
| `GET` | `/movies/{movieId}/showtimes?date=` | Public |
| `POST/PUT/DELETE` | `/movies`, `/movies/{movieId}` | Admin |
| `GET` | `/movies/{movieId}/reviews` | Optional |
| `POST` | `/movies/{movieId}/reviews` | Optional, `10/min` |
| `PUT/DELETE` | `/movies/{movieId}/reviews/{reviewId}` | JWT owner/policy |
| `POST` | `.../{reviewId}/react` | Optional, `30/min` |
| `POST` | `.../{reviewId}/reply` | Optional, `10/min` |
| `POST` | `.../{reviewId}/report` | Optional, `5/min` |
| `GET` | `/movies/reviews/moderation` | Admin |
| `PATCH` | `/movies/reviews/{reviewId}/moderate` | Admin |

Review index nhận filter/sort/pagination và trả `items`, `summary`, `pagination`. Mutation phải tuân validation rating/comment/media/spoiler/reaction/report của controller.

### Cinemas, rooms và snacks — 18 routes

| Method | URI | Access |
|---|---|---|
| `GET` | `/cinemas`, `/cinemas/{cinemaId}` | Public |
| `GET` | `/cinemas/{cinemaId}/movies` | Public |
| `GET` | `/cinemas/{cinemaId}/snacks` | Public |
| `GET` | `/cinemas/snacks?cinemaId=` | Public |
| `POST/PUT/DELETE` | `/cinemas`, `/cinemas/{cinemaId}` | Admin |
| `GET/POST` | `/cinemas/rooms` | Admin |
| `GET/PUT/DELETE` | `/cinemas/rooms/{roomId}` | Admin |
| `GET` | `/cinemas/snacks/all` | Admin |
| `GET` | `/cinemas/snacks/{snackId}` | Admin |
| `POST` | `/cinemas/snacks` | Admin |
| `PUT/DELETE` | `/cinemas/snacks/{snackId}` | Admin |

### Showtimes — 17 routes

Public showtime:

| Method | URI | Ghi chú |
|---|---|---|
| `GET` | `/showtimes` | List |
| `GET` | `/showtimes/search` | Global filtered search |
| `GET` | `/showtimes/options` | Formats/cities |
| `GET` | `/showtimes/{showtimeId}` | Detail |
| `GET` | `/showtimes/movie/{movieId}` | Theo phim |
| `GET` | `/showtimes/movie/{movieId}/upcoming` | Suất tương lai |
| `GET` | `/showtimes/movie/{movieId}/date-range` | `startDate`, `endDate` |
| `GET` | `/showtimes/room/{roomId}` | Theo phòng |

`/showtimes/search` query: `q`, `date`, `city`, `cinemaId`, `format`, `from`, `to`, `minSeats`, `page`, `perPage`.

Admin showtime:

| Method | URI |
|---|---|
| `POST` | `/showtimes` |
| `PUT/DELETE` | `/showtimes/{showtimeId}` |
| `PATCH` | `/showtimes/{showtimeId}/toggle-active` |
| `GET/POST/PUT` | `/showtimes/{showtimeId}/ticket-types` |
| `POST/DELETE` | `/showtimes/{showtimeId}/ticket-types/{ticketTypeId}` |

### Seats — 9 routes; showtime seats — 6 routes

| Method | URI | Access |
|---|---|---|
| `GET` | `/seats/layout?showtime_id=` | Public |
| `GET/POST` | `/seats` | Admin |
| `GET/PUT/DELETE` | `/seats/{seatId}` | Admin |
| `GET` | `/seats/room/{roomId}` | Admin |
| `POST` | `/seats/generate` | Admin |
| `GET` | `/seats/row-labels?rows=` | Admin |
| `GET` | `/showtime-seats/showtime/{showtimeId}` | Admin |
| `GET` | `/showtime-seats/showtime/{showtimeId}/available` | Admin |
| `POST` | `/showtime-seats/showtime/{showtimeId}/recalculate-prices` | Admin |
| `GET/PUT` | `/showtime-seats/{id}` | Admin |
| `PUT` | `/showtime-seats/{id}/reset` | Admin |

### Ticket types — 5 routes

| Method | URI | Access |
|---|---|---|
| `GET` | `/ticket-types?showtimeId=&userId=` | Public |
| `GET` | `/ticket-types/admin` | Admin |
| `POST` | `/ticket-types` | Admin |
| `PUT/DELETE` | `/ticket-types/{id}` | Admin |

### Seat locks — 3 routes

| Method | URI | Context |
|---|---|---|
| `POST` | `/seat-locks` | JWT hoặc `X-Session-Id` |
| `GET` | `/seat-locks/availability/{showtimeId}` | JWT hoặc session |
| `DELETE` | `/seat-locks/showtime/{showtimeId}` | JWT hoặc session |

Lock payload:

```json
{
  "showtimeId": "uuid",
  "seats": [{ "showtimeSeatId": "uuid", "ticketTypeId": "uuid" }]
}
```

### Bookings — 5 routes; checkout — 1 route

| Method | URI | Access |
|---|---|---|
| `POST` | `/bookings/price-preview` | Session context |
| `POST` | `/bookings/confirm` | Session context |
| `GET` | `/bookings/my-bookings` | JWT |
| `GET` | `/bookings/{bookingId}` | Optional JWT + owner/admin/capability check |
| `PATCH` | `/bookings/{bookingId}/qr` | JWT |
| `POST` | `/checkout` | Session context; confirm + initiate |

Preview:

```json
{ "lockId": "uuid", "promotionCode": null, "snacks": [{ "snackId": "uuid", "quantity": 1 }] }
```

Confirm:

```json
{
  "lockId": "uuid",
  "promotionCode": null,
  "snackCombos": [{ "snackId": "uuid", "quantity": 1 }],
  "guestInfo": { "email": "...", "username": "...", "phoneNumber": "..." }
}
```

Guest response có `guestAccessToken`. `GET /bookings/{id}` không phải public-by-ID: guest bắt buộc gửi token ở `X-Booking-Access-Token`.

### Payments — 7 routes

| Method | URI | Access | Contract |
|---|---|---|---|
| `POST` | `/payments/order` | Optional JWT, owner/capability, `20/min` | `{ bookingId, paymentMethod, amount? }` |
| `POST` | `/payments/order/capture` | Public, `30/min` | `{ transactionId, paymentMethod }` |
| `GET` | `/payments/order/status` | Public, `60/min` | Chỉ method `MOMO` |
| `GET/POST` | `/payments/momo/ipn` | Gateway callback | Verify signature/amount/order |
| `GET` | `/payments/search` | JWT | Admin toàn bộ; user chỉ dữ liệu của mình |
| `POST` | `/payments/{paymentId}/refund` | Admin | `{ reason }` |

Quy tắc an toàn:

- backend luôn dùng `booking.final_price`, không tin `amount` client;
- PayPal capture phải verify amount/currency;
- MoMo success chỉ được ghi từ IPN có chữ ký hợp lệ;
- callback/status phải idempotent.

### Promotions — 9 routes

| Method | URI | Access |
|---|---|---|
| `GET` | `/promotions`, `/active`, `/valid`, `/code/{code}`, `/{promotionId}` | Public |
| `POST` | `/promotions` | Admin |
| `PUT` | `/promotions/{promotionId}` | Admin |
| `PATCH` | `/promotions/{promotionId}/deactivate` | Admin |
| `DELETE` | `/promotions/{promotionId}` | Admin |

### Price base — 6; price modifiers — 7

Tất cả admin:

- `/price-base`, `/price-base/active`, `/price-base/{id}` với GET/POST/PUT/DELETE phù hợp;
- `/price-modifiers`, `/active`, `/by-condition`, `/{id}` với GET/POST/PUT/DELETE phù hợp.

### Hero slides — 6 routes

| Method | URI | Access |
|---|---|---|
| `GET` | `/hero-slides`, `/hero-slides/{heroSlideId}` | Public |
| `GET` | `/hero-slides/admin` | Admin |
| `POST` | `/hero-slides` | Admin |
| `PUT/DELETE` | `/hero-slides/{heroSlideId}` | Admin |

### Notifications — 5 routes

Tất cả JWT:

- `GET /notifications`
- `GET /notifications/unread-count`
- `PATCH /notifications/read-all`
- `PATCH /notifications/{notificationId}/read`
- `DELETE /notifications/{notificationId}`

### Contact — 1 route

`POST /contact/submissions` là public và throttle `8/min`.

## 4. Web routes

| Method | URI | Mô tả |
|---|---|---|
| `GET` | `/oauth2/authorization/google` | Bắt đầu Google OAuth |
| `GET` | `/login/oauth2/code/google` | Google callback |
| `GET` | `/up` | Health endpoint Laravel |

## 5. Status và HTTP semantics

| Domain | Values |
|---|---|
| Booking DB | `PENDING_PAYMENT`, `CONFIRMED`, `CANCELLED`, `EXPIRED` |
| Payment | `PENDING`, `COMPLETED`, `FAILED`, `CANCELLED`, `REFUND_PENDING`, `REFUNDED` |
| Seat | `AVAILABLE`, `LOCKED`, `BOOKED` |
| Movie | `SHOWING`, `UPCOMING` |
| Payment method | `PAYPAL`, `MOMO` |

HTTP status đáng chú ý:

- `401`: thiếu/sai/hết hạn JWT;
- `403`: sai role/ownership/capability;
- `404`: resource không tồn tại;
- `409`: seat/resource conflict;
- `410`: lock hết hạn;
- `422`: validation;
- `429`: throttle.

Chi tiết luồng tin cậy và transaction xem [FLOW.md](./FLOW.md).
