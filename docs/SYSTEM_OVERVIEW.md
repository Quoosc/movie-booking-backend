# Tổng quan hệ thống Backend

> Cập nhật theo mã nguồn và dependency đã cài ngày 2026-08-09.

## 1. Tech stack

| Thành phần | Hiện tại |
|---|---|
| PHP | Constraint `^8.2`; môi trường kiểm tra `8.3.16` |
| Laravel Framework | `12.65.0` (`^12.0`) |
| JWT | `firebase/php-jwt 7.1.0`, guard/middleware custom |
| Google OAuth | `laravel/socialite 5.29.0` |
| Redis client | `predis/predis 3.5.1` hoặc client theo env |
| Database dev | MySQL 8.x (`movie_booking_laravel`) |
| Payment | PayPal và MoMo |
| Asset | Cloudinary URL/public ID |
| Queue/cache | Database hoặc Redis theo environment |

## 2. Kiến trúc

```text
React frontend (:5173)
        │ JSON + Bearer/cookie/guest headers
        ▼
Laravel API (:8000 local hoặc :8080 qua Docker)
  Route → Middleware → Form Request → Controller
                                    → Service
                                    → Repository/Eloquent → MySQL
                                    → Redis lock/cache
                                    → PayPal/MoMo/Google/Mail
```

Code được tách thành 13 module nghiệp vụ. Xem cây thư mục tại [PROJECT_STRUCTURE.md](./PROJECT_STRUCTURE.md).

## 3. Chức năng hiện có

### Người dùng

- đăng ký, login email/password và Google OAuth;
- access/refresh token, logout một hoặc mọi phiên;
- profile, đổi mật khẩu, avatar;
- loyalty balance, transaction history và membership tiers;
- notifications đọc/chưa đọc/xóa;
- booking history và QR.

### Nội dung và khám phá

- CRUD/search/filter phim;
- rạp, phòng, ghế, snack;
- global showtime search theo phim/rạp/ngày/giờ/format/ghế trống;
- hero slides và contact submissions;
- phim liên quan;
- review, reply, reaction, report, spoiler/media và moderation audit.

### Booking/payment

- guest/member seat locking với TTL;
- price base, modifiers, ticket types và promotion;
- server-authoritative price preview/confirm;
- PayPal create/capture/refund;
- MoMo create order, signed IPN, status polling và refund;
- email/notification/loyalty khi booking được xác nhận;
- cleanup lock và booking hết hạn.

### Admin

CRUD/quản lý user, membership, movie, cinema, room, seat, showtime, ticket/pricing, snack, promotion, hero slide, booking/payment/refund và review moderation.

## 4. Authentication và authorization

### JWT lifecycle

```text
Login
  → verify password
  → access JWT + opaque refresh token
  → refresh token lưu DB và có thể phát bằng HttpOnly cookie

Protected request
  → Authorization: Bearer <accessToken>
  → JwtAuthMiddleware/JwtGuard verify signature, issuer, expiry
  → resolve current User

Refresh
  → verify refresh token/cookie
  → rotate token
  → trả access token và refresh token mới
```

`JwtSigningKey` bảo đảm key ký HS256 tối thiểu 32 byte. Nếu `JWT_SECRET` ngắn nhưng `APP_KEY` hợp lệ, hệ thống derive key an toàn và nhất quán cho cả encode/decode. Không đưa secret thật vào repository hoặc tài liệu.

### Quyền

| Middleware | Hành vi |
|---|---|
| `auth.jwt` | 401 nếu thiếu/sai/hết hạn token |
| `auth.optional` | Gắn user nếu token hợp lệ; cho guest qua nếu không có token |
| `role:admin` | 403 nếu current user không phải admin |

## 5. Guest booking security

Guest có hai loại định danh khác nhau:

1. `X-Session-Id`: sở hữu seat lock trong phiên checkout.
2. `X-Booking-Access-Token`: capability HMAC gắn với booking, được trả khi confirm guest booking.

`GET /api/bookings/{id}` và `POST /api/payments/order` chỉ cho phép owner/admin hoặc guest có capability token hợp lệ. So sánh token dùng `hash_equals`; biết UUID booking không đủ để đọc dữ liệu vé.

## 6. Payment trust model

### Server-authoritative amount

`PaymentController::initiatePayment` nhận `bookingId` và method, kiểm tra ownership/capability rồi luôn lấy `bookings.final_price`. Field `amount` từ client chỉ còn tương thích và không quyết định số tiền.

### PayPal

```text
Create order → browser approve → frontend callback
→ POST /api/payments/order/capture
→ backend gọi PayPal capture
→ verify status, amount, currency
→ lifecycle xác nhận booking/ghế/điểm/notification
```

### MoMo

```text
Create order → browser thanh toán
MoMo POST signed IPN → verify HMAC + orderId + amount
→ update payment/booking idempotently
Browser redirect → frontend poll GET /payments/order/status
```

Redirect query từ browser không bao giờ tự chuyển payment sang success. Endpoint capture legacy với method `MOMO` chỉ đọc trạng thái đã lưu.

## 7. Booking lifecycle

```text
Seat AVAILABLE
  → LOCKED (seat lock, mặc định 10 phút)
  → Booking PENDING_PAYMENT (mặc định 15 phút)
  → Payment success → Booking CONFIRMED + Seat BOOKED
  → Timeout/cancel → EXPIRED/CANCELLED + Seat AVAILABLE
  → Refund → payment/refund state + booking response REFUND_* + Seat AVAILABLE khi hoàn tất
```

Late payment được xử lý trong `CheckoutLifecycleService`; nếu ghế đã được người khác lấy, hệ thống không xác nhận chồng và có luồng automatic refund.

## 8. Pricing

```text
PriceBase active
  + PriceModifier theo DAY_OF_WEEK / SEAT_TYPE / SHOWTIME_FORMAT
  + TicketType modifier
  + Snack
  - Promotion/membership discount
  = final_price do backend tính
```

Giá concrete được lưu ở seat lock/booking seat để giữ snapshot, còn `showtime_seats.price_breakdown` lưu cách tính tại suất ghế.

## 9. Config quan trọng

| File | Env chính | Default/ý nghĩa |
|---|---|---|
| `config/jwt.php` | `JWT_SECRET`, `JWT_TTL`, `JWT_REFRESH_TTL`, `JWT_ISSUER` | TTL access mặc định 500 phút |
| `config/booking.php` | `BOOKING_LOCK_DURATION_MINUTES` | 10 phút |
| `config/booking.php` | `BOOKING_PAYMENT_TIMEOUT_MINUTES` | 15 phút |
| `config/booking.php` | `BOOKING_MAX_SEATS` | 10 ghế |
| `config/cinema.php` | `CINEMA_TIMEZONE` | `Asia/Ho_Chi_Minh` |
| `config/cors.php` | `CORS_ALLOWED_ORIGINS` | Danh sách frontend origins |
| `config/payment.php` | `PAYPAL_*`, `MOMO_*` | Credential/callback/gateway |
| `config/currency.php` | `PAYMENT_PAYPAL_CURRENCY` | Currency PayPal |
| `config/services.php` | `GOOGLE_*`, `FRONTEND_REDIRECT_URL` | OAuth |
| `config/database.php` | `REDIS_*` | Lock/cache/queue Redis |

## 10. Chạy local

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

Ở process khác nếu dùng async mail/notification và cleanup:

```powershell
php artisan queue:work
php artisan schedule:work
```

Kiểm tra:

```powershell
php artisan route:list
php artisan test
```

Không dùng credential sandbox/production thật trong tài liệu. Hướng dẫn tunnel xem [DEMO_CLOUDFLARE_TUNNEL.md](./DEMO_CLOUDFLARE_TUNNEL.md).
