# SYSTEM_OVERVIEW.md — Tổng quan hệ thống Backend

## 1. Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | Laravel | 11.x |
| Language | PHP | 8.2+ |
| Database | MySQL | 8.0+ (Laragon) |
| Authentication | JWT (custom) | firebase/php-jwt |
| Payment Gateway | PayPal REST API | Sandbox mode |
| Payment Gateway | Momo | Test endpoint |
| OAuth2 | Google Login | socialite |
| Cache/Queue | Database driver | (không dùng Redis) |
| Server | Laragon | Windows dev |

## 2. Kiến trúc hệ thống

```
                         ┌──────────────────┐
                         │   React Frontend │
                         │   :5173 (Vite)   │
                         └────────┬─────────┘
                                  │ HTTP (API calls)
                                  ▼
┌─────────────────────────────────────────────────────────┐
│                  Laravel Backend (:8000)                 │
│                                                         │
│  ┌──────────┐   ┌──────────┐   ┌──────────────────┐    │
│  │ Middleware│──→│Controller│──→│    Service        │    │
│  │ JWT/Role │   │          │   │ (Business Logic)  │    │
│  └──────────┘   └──────────┘   └────────┬─────────┘    │
│                                          │              │
│                                ┌─────────┴──────────┐   │
│                                │    Repository       │   │
│                                │  (Data Access)      │   │
│                                └─────────┬──────────┘   │
│                                          │              │
│                                ┌─────────┴──────────┐   │
│                                │   Model (Eloquent)  │   │
│                                │   → MySQL DB        │   │
│                                └────────────────────┘   │
│                                                         │
│  ┌──────────────────┐  ┌──────────────┐                 │
│  │ DTOs / Resources │  │ Transformers │                 │
│  │ (Input/Output)   │  │ (Response)   │                 │
│  └──────────────────┘  └──────────────┘                 │
└─────────────────────────────────────────────────────────┘
          │                    │
          ▼                    ▼
  ┌──────────────┐    ┌──────────────┐
  │ PayPal API   │    │  Momo API    │
  │ (sandbox)    │    │  (test)      │
  └──────────────┘    └──────────────┘
```

## 3. Authentication & Authorization

### 3.1 JWT Flow

```
Register → POST /api/auth/register
  → Hash password → Tạo user → Trả user info

Login → POST /api/auth/login
  → Verify credentials → Tạo accessToken (JWT) + refreshToken
  → Trả: { accessToken, refreshToken, user }

Protected Request:
  → Header: Authorization: Bearer <accessToken>
  → JwtAuthMiddleware decode JWT → set $request->user_id, $request->user_role
  → Controller xử lý

Token Refresh → GET /api/auth/refresh
  → Verify refreshToken → Tạo accessToken mới
```

### 3.2 Middleware Stack

| Middleware | Key | Mô tả |
|-----------|-----|-------|
| `JwtAuthMiddleware` | `auth.jwt` | Validate JWT, reject nếu invalid/expired |
| `OptionalJwtAuthMiddleware` | `auth.optional` | Parse JWT nếu có, không reject nếu không có |
| `CheckRole` | `role:admin` | Check `$request->user_role === 'ADMIN'` |

### 3.3 Guest Support

```
Guest (chưa login):
  → FE tạo UUID → gửi header: X-Session-Id: <uuid>
  → Backend dùng SessionContext DTO để resolve:
    - Nếu có JWT → type=USER, id=user_id
    - Nếu có X-Session-Id → type=GUEST_SESSION, id=session_id
    - Không có gì → reject
```

## 4. Payment System

### 4.1 Luồng thanh toán

```
FE gọi: POST /api/payments/order
  → PaymentController::initiatePayment()
  → Kiểm tra booking status = PENDING_PAYMENT
  → Tùy method:
    ├── PAYPAL:
    │   → PayPalService::createOrder()
    │   → Gọi PayPal REST API → tạo order
    │   → Trả paymentUrl (PayPal redirect)
    │   → FE redirect user → PayPal approve
    │   → PayPal callback → FE gọi capture
    │
    └── MOMO:
        → MomoService::createPayment()
        → Gọi Momo API → tạo order
        → Trả paymentUrl (Momo redirect)
        → User approve → Momo IPN callback

Capture: POST /api/payments/order/capture
  → PaymentController::capturePayment()
  → Tùy method:
    ├── PAYPAL: PayPalService::captureOrder(orderId)
    └── MOMO: Verify IPN data
  → Cập nhật payment status → COMPLETED
  → Cập nhật booking status → CONFIRMED
  → Cập nhật showtime_seats → BOOKED
  → Award loyalty points (nếu member)
```

### 4.2 Currency Conversion

```
VND (default) → PayPal yêu cầu USD
  → ExchangeRateService::convertVndToUsd(amount)
  → Fallback rate: 1 VND = 0.00004 USD (config)
  → Payment lưu cả 2:
    - amount (VND), currency (VND)
    - gateway_amount (USD), gateway_currency (USD)
    - exchange_rate (tỷ giá applied)
```

## 5. Pricing Engine

```
Giá vé = PriceBase (giá gốc) + Modifiers (phụ thu/giảm)

PriceCalculationService::calculatePrice(showtime, seat, ticketType):
  1. Lấy PriceBase active → base_price
  2. Query PriceModifiers active → filter theo điều kiện:
     - DAY_OF_WEEK: showtime day = WEEKEND? → phụ thu
     - SEAT_TYPE: seat = VIP? → phụ thu
     - SHOWTIME_FORMAT: format = 3D? → phụ thu
  3. Apply modifiers:
     - PERCENTAGE: price += price * modifier_value / 100
     - FIXED_AMOUNT: price += modifier_value
  4. Lưu price + price_breakdown vào showtime_seats
```

## 6. Booking Lifecycle

```
Trạng thái: PENDING_PAYMENT → CONFIRMED → (optional) REFUND_PENDING → REFUNDED
                            ↘ CANCELLED
                            ↘ EXPIRED (timeout)

CheckoutLifecycleService quản lý transitions:
  1. Lock Seats (10 phút TTL)
  2. Confirm Booking → status = PENDING_PAYMENT
  3. Payment:
     - Success → CONFIRMED
     - Timeout → EXPIRED (artisan schedule cleanup)
     - Cancel → CANCELLED
  4. Refund:
     - Request → REFUND_PENDING
     - Gateway confirm → REFUNDED
```

## 7. Config quan trọng

| Config | File | Key env | Default | Mô tả |
|--------|------|---------|---------|-------|
| JWT Secret | `config/jwt.php` | `JWT_SECRET` | — | Secret key cho JWT |
| JWT TTL | `config/jwt.php` | `JWT_TTL` | 500 min | Token lifetime |
| Seat Lock Duration | `config/booking.php` | `BOOKING_LOCK_DURATION_MINUTES` | 10 min | TTL lock ghế |
| Max Seats | `config/booking.php` | `BOOKING_MAX_SEATS` | 10 | Max ghế / booking |
| Payment Timeout | `config/booking.php` | `BOOKING_PAYMENT_TIMEOUT_MINUTES` | 15 min | Timeout thanh toán |
| PayPal Mode | `config/payment.php` | `PAYPAL_MODE` | sandbox | sandbox / live |
| Momo Endpoint | `config/payment.php` | `MOMO_API_ENDPOINT` | test URL | Momo gateway URL |
| Currency | `.env` | `CURRENCY_DEFAULT` | VND | Default currency |
| VND→USD Rate | `config/payment.php` | — | 0.00004 | Fallback exchange rate |

## 8. Cách chạy dự án

```bash
# 1. Clone + cài dependencies
composer install

# 2. Copy .env
cp .env.example .env
# Sửa DB_*, JWT_*, PAYPAL_*, MOMO_* theo môi trường

# 3. Generate app key
php artisan key:generate

# 4. Chạy migrations
php artisan migrate

# 5. (Optional) Seed data
php artisan db:seed

# 6. Start server
php artisan serve --port=8000

# Hoặc dùng Laragon → auto detect
```
