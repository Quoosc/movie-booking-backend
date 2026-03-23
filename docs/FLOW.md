# FLOW.md — Luồng dữ liệu Backend (Data Flow)

## 1. Request → Response Pipeline

```
HTTP Request (from Frontend)
  → routes/api.php (route matching)
  → Middleware stack:
    ├── CORS (config/cors.php)
    ├── auth.jwt (JwtAuthMiddleware) — nếu route protected
    ├── auth.optional (OptionalJwtAuthMiddleware) — nếu route cho guest
    └── role:admin (CheckRole) — nếu route admin-only
  → Form Request Validation (nếu có)
  → Controller method
  → Service (business logic)
  → Repository (data access) / Model (Eloquent query)
  → Transformer / Resource (format response)
  → JSON Response: { code, message, data }
```

## 2. Authentication Flow

### 2.1 Register

```
POST /api/auth/register
  → AuthController::register()
  → Validate: email unique, password confirm
  → Hash password (bcrypt, 12 rounds)
  → User::create() → UUID auto
  → Trả { success: true }
```

### 2.2 Login

```
POST /api/auth/login
  → AuthController::login()
  → UserService::findByEmail(email)
  → Hash::check(password, user.password)
  → Nếu đúng:
    → TokenService::createAccessToken(user) → JWT (TTL = 180 min)
    → TokenService::createRefreshToken(user) → lưu DB (refresh_tokens)
    → Trả { accessToken, refreshToken, user: { userId, email, role, ... } }
  → Nếu sai: 401 Unauthorized
```

### 2.3 JWT Middleware

```
JwtAuthMiddleware (auth.jwt):
  → Lấy Authorization: Bearer <token>
  → TokenService::decodeToken(token)
    → firebase/php-jwt decode
    → Verify exp, iss
  → Inject $request->user_id, $request->user_role
  → next($request)

OptionalJwtAuthMiddleware (auth.optional):
  → Nếu có token → decode + inject
  → Nếu không có token → vẫn cho qua (guest)
```

### 2.4 OAuth2 Google

```
GET /login/oauth2/code/google (web route)
  → OAuthController / Socialite
  → Google redirect → callback
  → Tìm user by email:
    - Có → login
    - Chưa có → tạo user mới (password = null, provider = google)
  → Tạo accessToken + refreshToken
  → Redirect FE: FRONTEND_REDIRECT_URL?token=xxx&refreshToken=xxx
```

## 3. Booking Flow (Backend)

### 3.1 Step 1: Lock Seats

```
POST /api/seat-locks
  → SeatLockController::lockSeats()
  → LockSeatsRequest validate: { showtimeId, seats: [{ showtimeSeatId, ticketTypeId }] }
  → SessionContext resolve (JWT user hoặc X-Session-Id guest)
  → BookingService::lockSeats():
    1. Check số ghế ≤ BOOKING_MAX_SEATS (config: 10)
    2. Query ShowtimeSeat WHERE id IN (...) AND seat_status = 'AVAILABLE'
    3. Nếu bất kỳ ghế nào không AVAILABLE → 409 Conflict
    4. PriceCalculationService::calculatePrice() cho mỗi ghế
    5. Tạo SeatLock record (TTL = 10 phút)
    6. Tạo SeatLockSeat records (mỗi ghế 1 record, lưu price)
    7. Update ShowtimeSeat.seat_status = 'LOCKED'
    8. Trả LockSeatsResponse: { lockId, expiresAt, remainingSeconds, seats }
```

### 3.2 Step 2: Price Preview

```
POST /api/bookings/price-preview
  → BookingController::pricePreview()
  → PricePreviewRequest validate: { lockId, snacks, promotionCode }
  → BookingService::previewPrice():
    1. Lấy SeatLock + SeatLockSeats → tính tổng tiền vé
    2. Lấy snacks → tính tổng tiền bắp nước
    3. Validate promotionCode → PromotionService::validate()
    4. Tính discount → finalPrice
    5. Trả breakdown: { seatTotal, snackTotal, discount, finalPrice }
```

### 3.3 Step 3: Confirm Booking

```
POST /api/bookings/confirm
  → BookingController::confirmBooking()
  → ConfirmBookingRequest validate: { lockId, promotionCode, snackCombos, guestInfo }
  → BookingService::confirmBooking():
    1. Validate SeatLock còn active + chưa expired
    2. Tạo Booking record (status = PENDING_PAYMENT)
    3. Tạo BookingSeat records (copy từ SeatLockSeat)
    4. Tạo BookingSnack records (snackCombos)
    5. Áp promotion → BookingPromotion record
    6. Set payment_expires_at = now + 15 phút (config)
    7. Trả BookingResponse
```

### 3.4 Step 4: Initiate Payment

```
POST /api/payments/order
  → PaymentController::initiatePayment()
  → InitiatePaymentRequest validate: { bookingId, paymentMethod, amount }
  → CheckoutService::initiatePayment():
    1. Verify booking status = PENDING_PAYMENT
    2. Tạo Payment record (status = PENDING)
    3. Switch paymentMethod:
       PAYPAL:
         → ExchangeRateService::convertVndToUsd(amount)
         → PayPalService::createOrder({ amount_usd, return_url, cancel_url })
         → Gọi PayPal REST API → nhận orderId
         → Payment.transaction_id = orderId
         → Trả { paymentUrl: "https://paypal.com/..." }

       MOMO:
         → MomoService::createPayment({ amount_vnd, orderId, ... })
         → Sign HMAC-SHA256
         → Gọi Momo API → nhận payUrl
         → Payment.transaction_id = requestId
         → Trả { paymentUrl: "https://momo.vn/..." }
```

### 3.5 Step 5: Capture Payment

```
POST /api/payments/order/capture
  → PaymentController::capturePayment()
  → ConfirmPaymentRequest validate: { transactionId, paymentMethod }
  → CheckoutService::capturePayment():
    1. Query Payment by transaction_id
    2. Switch method:
       PAYPAL:
         → PayPalService::captureOrder(orderId)
         → Verify capture status = COMPLETED
       MOMO:
         → Verify IPN signature / resultCode
    3. Payment.status = COMPLETED, completed_at = now
    4. Booking.status = CONFIRMED
    5. ShowtimeSeat.seat_status = BOOKED (cho tất cả ghế)
    6. Award loyalty points (nếu user authenticated)
    7. Deactivate SeatLock
    8. Trả PaymentResponse
```

### 3.6 Refund

```
POST /api/payments/{paymentId}/refund (Admin only)
  → RefundController::refund()
  → RefundRequest validate: { reason }
  → RefundService::processRefund():
    1. Verify payment status = COMPLETED
    2. Switch method:
       PAYPAL: → PayPalService::refund(captureId)
       MOMO: → MomoService::refund(orderId)
    3. Tạo Refund record
    4. Payment.status = REFUNDED
    5. Booking.status = REFUNDED, refunded = true
    6. ShowtimeSeat.seat_status = AVAILABLE (restore ghế)
```

### 3.7 Bảng tóm tắt Booking Flow

| Step | API | Service Method | DB Changes |
|------|-----|---------------|-----------|
| Lock | `POST /seat-locks` | `BookingService::lockSeats()` | SeatLock, SeatLockSeat, ShowtimeSeat→LOCKED |
| Preview | `POST /bookings/price-preview` | `BookingService::previewPrice()` | Read-only |
| Confirm | `POST /bookings/confirm` | `BookingService::confirmBooking()` | Booking, BookingSeat, BookingSnack, BookingPromotion |
| Pay Init | `POST /payments/order` | `CheckoutService::initiatePayment()` | Payment(PENDING), call gateway |
| Pay Capture | `POST /payments/order/capture` | `CheckoutService::capturePayment()` | Payment→COMPLETED, Booking→CONFIRMED, Seats→BOOKED |
| Refund | `POST /payments/{id}/refund` | `RefundService::processRefund()` | Refund, Payment→REFUNDED, Booking→REFUNDED, Seats→AVAILABLE |

## 4. Pricing Flow

```
Admin tạo PriceBase (giá gốc, ví dụ: 90.000 VND)
Admin tạo PriceModifiers:
  - { conditionType: "DAY_OF_WEEK", conditionValue: "WEEKEND", modifierType: "PERCENTAGE", modifierValue: 20 }
  - { conditionType: "SEAT_TYPE", conditionValue: "VIP", modifierType: "FIXED_AMOUNT", modifierValue: 30000 }
  - { conditionType: "SHOWTIME_FORMAT", conditionValue: "3D", modifierType: "FIXED_AMOUNT", modifierValue: 20000 }

Khi tạo showtime (hoặc recalculate):
  → PriceCalculationService::calculatePrice(showtime, seat):
    base = 90000 (PriceBase active)
    IF weekend → +20% → 108000
    IF VIP seat → +30000 → 138000
    IF 3D format → +20000 → 158000
    → Lưu vào showtime_seats.price = 158000
    → Lưu showtime_seats.price_breakdown = JSON { base, modifiers: [...] }
```

## 5. Momo IPN Flow

```
Momo gọi callback:
  POST /api/payments/momo/ipn
  → PaymentController::handleMomoIpnPost()
  → Verify HMAC signature
  → resultCode == 0 → success
  → Update payment + booking status
  → Trả 204 No Content

FE cũng gọi capture riêng (Momo redirect về FE):
  POST /api/payments/order/capture
  → Verify lại với Momo API
```

## 6. Seat Lock Expiry

```
SeatLock TTL = 10 phút (config/booking.php)

Khi lock expired:
  → BookingService kiểm tra expires_at < now
  → SeatLock.active = false
  → ShowtimeSeat.seat_status = AVAILABLE (restore)

Khi payment timeout (15 phút):
  → Booking.payment_expires_at < now
  → Booking.status = EXPIRED
  → ShowtimeSeat.seat_status = AVAILABLE
```
