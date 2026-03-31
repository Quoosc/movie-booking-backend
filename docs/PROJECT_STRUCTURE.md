# PROJECT_STRUCTURE.md — Cấu trúc dự án Backend Laravel (Modular)

## Tổng quan

Dự án sử dụng Laravel 11 (PHP 8.2+) với kiến trúc modular theo domain.

- Domain code đặt trong `app/Modules/<Domain>`.
- Thành phần framework/core đặt trong `app/Core`.
- Thành phần shared cross-domain đặt trong `app/Shared`.
- API routes được tách file theo domain trong `routes/api/*.php` và nạp qua `routes/api.php`.

## Cây thư mục chính

```text
movie-booking-backend-laravel/
├── app/
│   ├── Core/
│   │   ├── Auth/
│   │   │   └── JwtGuard.php
│   │   ├── Helpers/
│   │   │   └── SessionHelper.php
│   │   └── Http/
│   │       ├── Controllers/
│   │       │   └── BaseController.php
│   │       └── Middleware/
│   │           ├── JwtAuthMiddleware.php
│   │           ├── OptionalJwtAuthMiddleware.php
│   │           ├── CheckRole.php
│   │           └── EncryptCookies.php
│   │
│   ├── Shared/
│   │   ├── Services/
│   │   │   ├── TokenService.php
│   │   │   ├── ExchangeRateService.php
│   │   │   └── RedisLockService.php
│   │   └── Support/
│   │       └── SecurityUtils.php
│   │
│   ├── Modules/
│   │   ├── Booking/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   ├── DTO/
│   │   │   ├── Repositories/
│   │   │   ├── Services/
│   │   │   └── Transformers/
│   │   ├── Payment/
│   │   │   ├── Controllers/
│   │   │   │   └── Gateways/
│   │   │   ├── DTO/
│   │   │   ├── Requests/
│   │   │   ├── Repositories/
│   │   │   ├── Services/
│   │   │   └── Transformers/
│   │   ├── Movie/
│   │   │   ├── Controllers/
│   │   │   └── Resources/
│   │   ├── User/
│   │   │   ├── Controllers/
│   │   │   └── Services/
│   │   ├── Membership/
│   │   │   ├── Controllers/
│   │   │   ├── Resources/
│   │   │   └── Services/
│   │   ├── Cinema/
│   │   │   ├── Controllers/
│   │   │   ├── Resources/
│   │   │   └── Services/
│   │   ├── Showtime/
│   │   │   ├── Controllers/
│   │   │   ├── Repositories/
│   │   │   ├── Resources/
│   │   │   ├── Services/
│   │   │   └── Transformers/
│   │   ├── Ticketing/
│   │   │   ├── Controllers/
│   │   │   ├── Resources/
│   │   │   └── Services/
│   │   ├── Pricing/
│   │   │   ├── Controllers/
│   │   │   ├── Resources/
│   │   │   └── Services/
│   │   └── Promotion/
│   │       ├── Controllers/
│   │       ├── Resources/
│   │       └── Services/
│   │
│   ├── Console/
│   ├── DTO/
│   ├── Enums/
│   ├── Exceptions/
│   ├── Models/
│   ├── Providers/
│   └── ValueObjects/
│
├── routes/
│   ├── api.php
│   ├── api/
│   │   ├── auth.php
│   │   ├── movie.php
│   │   ├── cinema.php
│   │   ├── showtime.php
│   │   ├── ticketing.php
│   │   ├── pricing.php
│   │   ├── promotion.php
│   │   ├── booking.php
│   │   ├── payment.php
│   │   ├── user.php
│   │   ├── membership.php
│   │   ├── admin.php
│   │   └── misc.php
│   ├── web.php
│   └── console.php
│
├── config/
├── database/
├── docs/
└── tests/
```

## Quy ước tổ chức mã

1. Mỗi domain module tự quản lý Controller/Request/Service/Repository/Resource của riêng nó.
2. Logic dùng chung qua nhiều domain đặt ở `app/Shared`.
3. Thành phần technical framework-level (guard, middleware, base controller) đặt ở `app/Core`.
4. Model Eloquent hiện vẫn tập trung ở `app/Models` để tương thích Laravel mặc định.
5. `routes/api.php` chỉ làm nhiệm vụ nạp các file route con theo domain.

## Routing modular

File `routes/api.php` hiện không chứa route business trực tiếp, mà chỉ `require` các file:

- `routes/api/auth.php`
- `routes/api/movie.php`
- `routes/api/cinema.php`
- `routes/api/showtime.php`
- `routes/api/ticketing.php`
- `routes/api/promotion.php`
- `routes/api/booking.php`
- `routes/api/payment.php`
- `routes/api/user.php`
- `routes/api/membership.php`
- `routes/api/pricing.php`
- `routes/api/admin.php`
- `routes/api/misc.php`

## Trạng thái xác thực sau refactor

- Route registry: chạy thành công (`php artisan route:list`).
- Test suite hiện tại: pass (`php artisan test`):
    - 2 tests passed
    - 2 assertions

## Ghi chú

- Cấu trúc đã chuyển từ monolithic `app/Http/Controllers` và `app/Services` sang domain modules.
- Nếu bổ sung domain mới, tạo thêm `app/Modules/<NewDomain>` và `routes/api/<new-domain>.php`, sau đó include trong `routes/api.php`.
