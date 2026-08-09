# Luồng dữ liệu Backend

> Cập nhật theo mã nguồn ngày 2026-08-09.

## 1. Request pipeline

```text
HTTP request
  → API/web route
  → CORS + throttle
  → auth.jwt / auth.optional
  → role:admin nếu cần
  → Form Request hoặc controller validation
  → Controller
  → Domain Service
  → Repository/Eloquent + DB transaction
  → Resource/Transformer/JSON
```

Response hiện có cả dạng envelope `{ code, message, data }` và object trực tiếp ở một số endpoint booking/payment legacy. HTTP status là nguồn quyết định thành công/thất bại.

## 2. Auth flow

### Register/login

```text
POST /api/auth/register
  → validate unique email/password confirmation
  → hash password
  → tạo User UUID

POST /api/auth/login
  → throttle
  → tìm user + Hash::check
  → TokenService tạo access JWT
  → tạo/ghi refresh token trong DB
  → phát refresh cookie nếu cấu hình
  → trả token + user
```

`TokenService` và `JwtGuard` cùng resolve key qua `JwtSigningKey`, nên không còn trường hợp bên ký và bên verify dùng key khác nhau. Key ngắn được derive từ `APP_KEY`; nếu không có tối thiểu 32 byte entropy hệ thống dừng với lỗi cấu hình rõ ràng.

### Refresh/logout

```text
GET hoặc POST /api/auth/refresh
  → đọc refresh cookie hoặc body token
  → verify DB token + expiry
  → rotate refresh token
  → trả access/refresh token mới

POST /api/auth/logout
  → thu hồi phiên hiện tại

POST /api/auth/logout-all (JWT)
  → thu hồi mọi refresh token của current user
```

### Google OAuth

```text
GET /oauth2/authorization/google
  → Socialite redirect Google
GET /login/oauth2/code/google
  → upsert user theo email/provider
  → tạo token
  → redirect FRONTEND_REDIRECT_URL?token=...&refreshToken=...
```

## 3. Session context và guest

`SessionHelper` resolve context theo thứ tự:

1. JWT hợp lệ → owner type `USER`, ID là `user_id`.
2. Không có user nhưng có `X-Session-Id` hợp lệ → `GUEST_SESSION`.
3. Không có cả hai → từ chối ở nghiệp vụ cần owner.

`X-Session-Id` chỉ chứng minh ownership của seat lock; sau khi booking được tạo, guest nhận `guestAccessToken` HMAC riêng và phải gửi `X-Booking-Access-Token` để đọc booking/tạo payment.

## 4. Booking flow

### Lock seats

```text
POST /api/seat-locks
  → validate showtimeId + seats/ticketTypeId
  → resolve SessionContext
  → DB transaction
      1. kiểm tra số ghế ≤ BOOKING_MAX_SEATS
      2. xác thực showtime/ticket type/ghế
      3. lấy distributed locks qua Redis khi khả dụng
      4. từ chối ghế không AVAILABLE (409)
      5. tính price snapshot cho từng ghế
      6. tạo seat_locks + seat_lock_seats
      7. showtime_seats → LOCKED
  → trả lockId, expiresAt, remainingSeconds
```

Redis giảm race giữa process; database transaction và status là nguồn trạng thái bền vững.

### Price preview

```text
POST /api/bookings/price-preview
  → xác minh lock còn hạn và thuộc current session
  → seat snapshot + snacks
  → validate promotion/membership
  → trả seatTotal, snackTotal, discount, finalPrice, breakdown
```

### Confirm

```text
POST /api/bookings/confirm
  → validate lock/session/guestInfo
  → transaction
      1. tạo bookings(PENDING_PAYMENT)
      2. copy seat lock thành booking_seats
      3. tạo booking_snacks/booking_promotions
      4. set payment_expires_at
      5. tạo QR payload
  → member: trả booking
  → guest: trả booking + guestAccessToken
```

`POST /api/checkout` gộp confirm booking và initiate payment thành một request; các endpoint tách vẫn tồn tại và được frontend hiện tại sử dụng.

### Booking detail security

```text
GET /api/bookings/{bookingId} (auth.optional)
  → not found: 404
  → admin hoặc booking.user_id == current user: 200
  → guest capability token HMAC hợp lệ: 200
  → còn lại: 403
```

## 5. Payment flow

### Initiate

```text
POST /api/payments/order
  → optional JWT + throttle
  → tìm booking
  → xác minh owner/admin hoặc guest capability token
  → bỏ qua authority của amount từ client
  → lấy booking.final_price
  → PAYPAL: convert VND → gateway currency, create order
  → MOMO: sign create-order request, create order
  → lưu Payment(PENDING) + transaction ID
  → trả paymentUrl
```

### PayPal capture

```text
POST /api/payments/order/capture { transactionId, paymentMethod: PAYPAL }
  → PayPalService::captureOrder
  → verify order/capture status
  → verify amount + currency với payment snapshot
  → CheckoutLifecycleService::handleSuccessfulPayment
      Payment → COMPLETED
      Booking → CONFIRMED
      ShowtimeSeat → BOOKED
      deactivate/release lock
      award loyalty once
      gửi mail + notification
```

### MoMo signed IPN

```text
POST /api/payments/momo/ipn
  → build canonical raw data
  → verify HMAC-SHA256 bằng MOMO_SECRET_KEY
  → tìm payment theo order/request ID
  → verify amount trùng payment.amount
  → xử lý resultCode idempotently
  → lifecycle success/failure

GET /api/payments/order/status?transactionId=...&paymentMethod=MOMO
  → chỉ đọc persisted state cho frontend poll
```

`POST /payments/order/capture` với method MoMo là endpoint tương thích chỉ gọi verify/read; không tin dữ liệu redirect của browser và không tự capture.

### Refund

```text
POST /api/payments/{paymentId}/refund (admin)
  → validate payment/refund eligibility
  → PayPal hoặc MoMo refund API
  → refunds record
  → cập nhật payment/booking refund state
  → trả ghế AVAILABLE khi refund hoàn tất
```

## 6. Expiry và late payment

Scheduler chạy `locks:cleanup-expired` mỗi phút:

- lock hết hạn → inactive/xóa theo command, release Redis, ghế về `AVAILABLE`;
- booking `PENDING_PAYMENT` quá `payment_expires_at` → `EXPIRED`, giải phóng ghế.

Nếu payment về muộn:

- lifecycle thử bảo toàn tính nhất quán;
- nếu ghế vẫn lấy lại được, có thể xác nhận theo policy;
- nếu ghế đã được booking khác chiếm, không double-book và kích hoạt automatic refund.

## 7. Review flow

```text
GET /movies/{id}/reviews (auth.optional)
  → published reviews + reply tree + reaction state
  → summary/distribution + pagination

POST review/reaction/reply/report
  → optional JWT + IP/user identity + throttle
  → validate ownership/rate/duplicate constraints
  → reply có thể tạo notification cho tác giả review

Admin moderation
  → GET /movies/reviews/moderation
  → PATCH /movies/reviews/{id}/moderate
  → cập nhật status + ghi review_moderation_logs
```

## 8. Loyalty và notifications

```text
Booking confirmed
  → award loyalty đúng một lần
  → cập nhật users.loyalty_points
  → ghi loyalty_transactions với balance_after/source
  → Laravel database notification BookingConfirmed

Điểm bị điều chỉnh/refund
  → transaction cộng/trừ tương ứng
  → notification LoyaltyPointsChanged
```

Notification dùng Laravel database notifications. API hiện là request/poll; backend chưa cấu hình broadcast/WebSocket channel.

## 9. Showtime search

```text
GET /api/showtimes/search
  → validate q/date/city/cinemaId/format/from/to/minSeats/page/perPage
  → lọc suất active/tương lai theo cinema timezone
  → aggregate available seats + minimum price
  → paginate item có movie/cinema/room/showtime

GET /api/showtimes/options
  → formats + cities đang có trong suất tương lai
```

Timezone nghiệp vụ lấy từ `config/cinema.php`, mặc định `Asia/Ho_Chi_Minh`.

## 10. Contact và assets

- `POST /contact/submissions` validate + throttle, lưu source/IP/user-agent/attachment URL và gửi mail nếu bật.
- Cloudinary upload diễn ra ở frontend; backend chỉ lưu URL/public ID cho movie, cinema, snack, avatar, hero slide và review media.

Chi tiết route xem [API_SPEC.md](./API_SPEC.md); schema xem [DATABASE.md](./DATABASE.md).
