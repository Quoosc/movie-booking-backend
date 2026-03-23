# PROJECT_STRUCTURE.md — Cấu trúc dự án Backend Laravel

## Tổng quan

Dự án sử dụng **Laravel 11** (PHP 8.2+) với kiến trúc **Service-Repository pattern**.
Database: **MySQL** (`movie_booking_laravel`). Authentication: **JWT** (custom middleware).
Payment: **PayPal** (sandbox) + **Momo**. OAuth2: **Google**.

```
movie-booking-backend-laravel/
├── app/
│   ├── Auth/                        # JWT token helper
│   ├── Console/                     # Artisan commands
│   ├── DTO/                         # Data Transfer Objects
│   │   ├── Bookings/
│   │   │   ├── LockSeatsRequest.php
│   │   │   └── LockSeatsResponse.php
│   │   ├── Payments/
│   │   │   ├── InitiatePaymentRequest.php
│   │   │   ├── InitiatePaymentResponse.php
│   │   │   ├── IpnResponse.php
│   │   │   └── PaymentResponse.php
│   │   └── SessionContext.php       # User/Guest session context
│   │
│   ├── Enums/                       # PHP 8.1 backed enums
│   │   ├── BookingStatus.php        # PENDING_PAYMENT, CONFIRMED, CANCELLED, EXPIRED
│   │   ├── PaymentStatus.php        # PENDING, COMPLETED, FAILED, REFUNDED, CANCELLED, REFUND_PENDING
│   │   ├── PaymentMethod.php        # PAYPAL, MOMO
│   │   ├── SeatStatus.php           # AVAILABLE, LOCKED, BOOKED
│   │   ├── RefundStatus.php         # PENDING, COMPLETED, FAILED
│   │   └── LockOwnerType.php        # USER, GUEST_SESSION
│   │
│   ├── Exceptions/                  # Custom exception handlers
│   ├── Helpers/                     # Utility functions
│   │
│   ├── Http/
│   │   ├── Controllers/             # 23 controllers
│   │   │   ├── AuthController.php           # Login, register, logout, refresh
│   │   │   ├── MovieController.php          # CRUD + public browsing + showtimes
│   │   │   ├── CinemaController.php         # CRUD cinemas, rooms, snacks
│   │   │   ├── ShowtimeController.php       # CRUD showtimes
│   │   │   ├── SeatController.php           # CRUD seats + layout + generate
│   │   │   ├── ShowtimeSeatController.php   # Showtime seat status/price
│   │   │   ├── TicketTypeController.php     # Ticket type CRUD
│   │   │   ├── ShowtimeTicketTypeController.php  # Assign ticket types to showtimes
│   │   │   ├── PriceBaseController.php      # Base pricing CRUD
│   │   │   ├── PriceModifierController.php  # Price modifier CRUD
│   │   │   ├── PromotionController.php      # Promotion CRUD + validate
│   │   │   ├── SeatLockController.php       # Lock/release seats
│   │   │   ├── BookingController.php        # Price preview, confirm, history
│   │   │   ├── CheckoutController.php       # One-step checkout
│   │   │   ├── PaymentController.php        # Initiate + capture payment
│   │   │   ├── RefundController.php         # Refund processing
│   │   │   ├── UsersController.php          # Profile + admin user management
│   │   │   ├── MembershipTierController.php # Membership tier CRUD
│   │   │   ├── OAuthController.php          # Google OAuth2
│   │   │   └── Payments/
│   │   │       ├── MomoController.php       # Momo-specific logic
│   │   │       └── PayPalController.php     # PayPal-specific logic
│   │   │
│   │   ├── Middleware/
│   │   │   ├── JwtAuthMiddleware.php        # auth.jwt — validate JWT token
│   │   │   ├── OptionalJwtAuthMiddleware.php # auth.optional — JWT if present
│   │   │   ├── CheckRole.php                # role:admin — check ADMIN role
│   │   │   └── EncryptCookies.php
│   │   │
│   │   ├── Requests/                # Form Request Validation
│   │   │   ├── LockSeatsRequest.php
│   │   │   ├── PricePreviewRequest.php
│   │   │   ├── ConfirmBookingRequest.php
│   │   │   ├── CheckoutRequest.php
│   │   │   ├── InitiatePaymentRequest.php
│   │   │   ├── ConfirmPaymentRequest.php
│   │   │   ├── PaymentSearchRequest.php
│   │   │   ├── RefundRequest.php
│   │   │   └── UpdateQrCodeRequest.php
│   │   │
│   │   └── Resources/              # API Resource Transformers
│   │       ├── MovieResource.php
│   │       ├── CinemaResource.php
│   │       ├── RoomResource.php
│   │       ├── ShowtimeResource.php
│   │       ├── SeatResource.php
│   │       ├── ShowtimeSeatResource.php
│   │       ├── SnackResource.php
│   │       ├── TicketTypeResource.php
│   │       ├── TicketTypePublicResource.php
│   │       ├── PriceBaseResource.php
│   │       ├── PriceModifierResource.php
│   │       ├── PromotionResource.php
│   │       └── MembershipTierResource.php
│   │
│   ├── Models/                      # 23 Eloquent models
│   │   ├── User.php
│   │   ├── Movie.php, Cinema.php, Room.php
│   │   ├── Showtime.php, Seat.php, ShowtimeSeat.php
│   │   ├── TicketType.php, ShowtimeTicketType.php
│   │   ├── SeatLock.php, SeatLockSeat.php
│   │   ├── PriceBase.php, PriceModifier.php
│   │   ├── Promotion.php
│   │   ├── Booking.php, BookingSeat.php, BookingSnack.php, BookingPromotion.php
│   │   ├── Snack.php
│   │   ├── Payment.php, Refund.php
│   │   ├── MembershipTier.php, RefreshToken.php
│   │
│   ├── Repositories/                # Data access layer
│   │   ├── BookingRepository.php
│   │   ├── PaymentRepository.php
│   │   ├── RefundRepository.php
│   │   ├── SeatLockRepository.php
│   │   └── ShowtimeSeatRepository.php
│   │
│   ├── Services/                    # 22 business logic services
│   │   ├── BookingService.php       # Core booking logic
│   │   ├── CheckoutService.php      # Checkout orchestration
│   │   ├── CheckoutLifecycleService.php  # Checkout state transitions
│   │   ├── CinemaService.php
│   │   ├── ExchangeRateService.php  # VND ↔ USD conversion
│   │   ├── MembershipTierService.php
│   │   ├── MomoService.php          # Momo payment gateway
│   │   ├── PayPalService.php        # PayPal payment gateway
│   │   ├── PriceCalculationService.php  # Dynamic pricing engine
│   │   ├── PriceBaseService.php
│   │   ├── PriceModifierService.php
│   │   ├── PromotionService.php
│   │   ├── RedisLockService.php     # Distributed locking
│   │   ├── RefundService.php
│   │   ├── SeatLayoutService.php    # Seat layout for showtime
│   │   ├── SeatService.php          # Seat CRUD + generate
│   │   ├── ShowtimeSeatService.php
│   │   ├── ShowtimeTicketTypeService.php
│   │   ├── TicketTypeService.php
│   │   ├── TokenService.php         # JWT token creation/validation
│   │   └── UserService.php
│   │
│   ├── Transformers/                # Response transformers
│   │   ├── BookingTransformer.php
│   │   ├── PaymentTransformer.php
│   │   └── ShowtimeSeatTransformer.php
│   │
│   ├── ValueObjects/                # Immutable value types
│   ├── Support/                     # Support utilities
│   └── Providers/                   # Service providers
│
├── config/
│   ├── jwt.php                      # JWT secret, TTL, issuer
│   ├── payment.php                  # PayPal + Momo + exchange rate config
│   ├── booking.php                  # Lock duration, max seats, payment timeout
│   ├── cors.php                     # CORS configuration
│   ├── currency.php                 # Currency settings
│   └── ...                          # Standard Laravel configs
│
├── database/
│   ├── migrations/                  # 32 migration files → 23 tables
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── api.php                      # Tất cả API routes (340 lines)
│   ├── web.php                      # OAuth2 callback routes
│   └── console.php
│
└── .env                             # Environment configuration
```

## Quy ước quan trọng

| Concept | Quy tắc |
|---------|---------|
| **Primary Key** | Tất cả bảng dùng **UUID** |
| **Auth** | JWT custom middleware (`auth.jwt`) — không dùng Sanctum/Passport |
| **Guest support** | Header `X-Session-Id` cho guest checkout, middleware `auth.optional` |
| **Admin guard** | Middleware chain: `auth.jwt` + `role:admin` |
| **Response format** | `{ code: 200, message: "...", data: {...} }` |
| **API prefix** | `/api/...` (Laravel auto-prefix) |
| **Enum** | PHP 8.1 backed enums (`BookingStatus`, `PaymentMethod`, ...) |
| **Pricing** | Dynamic: PriceBase → PriceModifiers → final price per seat |
| **Currency** | VND (default), auto-convert sang USD cho PayPal |
