# Cấu trúc dự án Backend Laravel

> Cập nhật theo mã nguồn ngày 2026-08-09.

## 1. Tổng quan

Backend dùng Laravel 12 với kiến trúc modular theo domain:

- nghiệp vụ nằm trong `app/Modules/<Domain>`;
- middleware/guard/helper cấp framework nằm trong `app/Core`;
- service/support dùng chung nằm trong `app/Shared`;
- model Eloquent tập trung tại `app/Models`;
- API route tách theo domain trong `routes/api/*.php` và được require từ `routes/api.php`.

Snapshot hiện tại:

| Hạng mục | Số lượng |
|---|---:|
| Domain modules | 13 |
| Model Eloquent | 30 |
| Migration | 47 |
| Route files dưới `routes/api` | 15 |
| Toàn bộ routes | 152 |
| API routes | 146 |
| API có `auth.jwt` | 95 |
| API có `role:admin` | 75 |

## 2. Cây thư mục

```text
movie-booking-backend-laravel/
├── app/
│   ├── Core/
│   │   ├── Auth/JwtGuard.php
│   │   ├── Helpers/SessionHelper.php
│   │   └── Http/
│   │       ├── Controllers/BaseController.php
│   │       └── Middleware/
│   │           ├── JwtAuthMiddleware.php
│   │           ├── OptionalJwtAuthMiddleware.php
│   │           ├── CheckRole.php
│   │           └── EncryptCookies.php
│   ├── Shared/
│   │   ├── Services/
│   │   │   ├── TokenService.php
│   │   │   ├── ExchangeRateService.php
│   │   │   └── RedisLockService.php
│   │   └── Support/
│   │       ├── JwtSigningKey.php
│   │       └── SecurityUtils.php
│   ├── Modules/
│   │   ├── Booking/
│   │   ├── Cinema/
│   │   ├── Contact/
│   │   ├── HeroSlide/
│   │   ├── Membership/
│   │   ├── Movie/
│   │   ├── Notification/
│   │   ├── Payment/
│   │   ├── Pricing/
│   │   ├── Promotion/
│   │   ├── Showtime/
│   │   ├── Ticketing/
│   │   └── User/
│   ├── Console/Commands/
│   ├── DTO/
│   ├── Enums/
│   ├── Exceptions/
│   ├── Models/
│   ├── Notifications/
│   ├── Providers/
│   └── ValueObjects/
├── bootstrap/
├── config/
│   ├── booking.php
│   ├── cinema.php
│   ├── cors.php
│   ├── currency.php
│   ├── jwt.php
│   ├── momo.php
│   ├── payment.php
│   └── paypal.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
├── routes/
│   ├── api.php
│   ├── api/
│   │   ├── admin.php
│   │   ├── auth.php
│   │   ├── booking.php
│   │   ├── cinema.php
│   │   ├── contact.php
│   │   ├── hero-slide.php
│   │   ├── membership.php
│   │   ├── movie.php
│   │   ├── notification.php
│   │   ├── payment.php
│   │   ├── pricing.php
│   │   ├── promotion.php
│   │   ├── showtime.php
│   │   ├── ticketing.php
│   │   └── user.php
│   ├── console.php
│   └── web.php
├── tests/
├── docker-compose.yml
├── Dockerfile
├── composer.json
└── artisan
```

File `routes/api/misc.php` trong tài liệu cũ đã bị loại bỏ; contact, hero slide và notification có route file riêng.

## 3. Pattern bên trong module

Không phải module nào cũng cần đủ mọi thư mục, nhưng pattern chung là:

```text
app/Modules/<Domain>/
├── Controllers/       # HTTP boundary
├── Requests/          # Validation
├── DTO/               # Input/output value objects
├── Services/          # Business logic
├── Repositories/      # Data access/query
├── Resources/         # Laravel API resource
└── Transformers/      # Map response legacy/current
```

Controller nên mỏng: validate/resolve context → gọi service → format HTTP response. Transaction, idempotency và state transition thuộc service/repository.

## 4. Route registration

`routes/api.php` require đúng 15 file route ở trên. Các route tự có prefix `/api` do Laravel bootstrap.

| Middleware | Mục đích |
|---|---|
| `api` | Nhóm middleware API mặc định |
| `auth.jwt` | Bắt buộc access token hợp lệ |
| `auth.optional` | Parse JWT nếu có, vẫn cho guest qua |
| `role:admin` | Bắt buộc role `ADMIN` |
| `throttle:*` | Giới hạn auth, review, contact và payment callback/status |

Google OAuth nằm ở web routes:

- `GET /oauth2/authorization/google`
- `GET /login/oauth2/code/google`

## 5. Core và Shared đáng chú ý

### JWT

`TokenService` và `JwtGuard` cùng dùng `JwtSigningKey::resolve()` để tránh lỗi “provided key is too short” và đảm bảo HS256 có tối thiểu 32 byte entropy. Helper:

1. giải mã secret có prefix `base64:`;
2. dùng `JWT_SECRET` nếu đủ dài;
3. nếu secret ngắn, derive HMAC-SHA256 từ `APP_KEY` và secret cấu hình;
4. ném lỗi rõ ràng nếu cả hai nguồn không đủ an toàn.

### Guest/session

`SessionHelper` tạo `SessionContext` từ JWT hoặc `X-Session-Id`. Guest booking còn có capability token riêng (`X-Booking-Access-Token`) để chống đọc booking theo UUID.

### Lock và pricing

- `RedisLockService` hỗ trợ lock phân tán/TTL.
- Database vẫn lưu `seat_locks`, `seat_lock_seats` và `showtime_seats` làm trạng thái nghiệp vụ.
- `ExchangeRateService` phục vụ chuyển VND sang currency của PayPal.

## 6. Console và scheduler

`locks:cleanup-expired` được schedule mỗi phút trong `routes/console.php`. Command:

- giải phóng lock hết hạn;
- đưa ghế về `AVAILABLE`;
- release Redis lock;
- chuyển booking quá hạn thanh toán sang `EXPIRED`.

Khi deploy phải chạy scheduler (`php artisan schedule:work` hoặc cron `schedule:run`) và queue worker nếu notification/mail chạy queue.

## 7. Quy ước thêm domain mới

1. Tạo `app/Modules/<Domain>` với các layer cần thiết.
2. Tạo model/migration trong thư mục Laravel chuẩn.
3. Tạo `routes/api/<domain>.php`, require từ `routes/api.php`.
4. Áp `auth.jwt` và `role:admin` ở route, không chỉ kiểm tra ở frontend.
5. Dùng Form Request cho payload phức tạp.
6. Bọc mutation nhiều bảng trong transaction.
7. Thêm feature/unit test cho happy path, validation, ownership/role và idempotency.
8. Cập nhật [API_SPEC.md](./API_SPEC.md), [DATABASE.md](./DATABASE.md) và [FLOW.md](./FLOW.md).

## 8. Lệnh kiểm tra cấu trúc

```powershell
php artisan route:list
php artisan migrate:status
php artisan test
vendor\bin\pint --test
```
