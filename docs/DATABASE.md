# DATABASE.md — Sơ đồ Database & Chi tiết bảng

## Tổng quan

- **Database Engine**: MySQL 8.0+ (Laragon)
- **Database Name**: `movie_booking_laravel`
- **Primary Keys**: Tất cả dùng **UUID** (char(36))
- **Timestamps**: Đa số bảng có `created_at`, `updated_at`
- **Tổng số tables**: 23

---

## 1. Entity Relationship (Text)

```
Users ──1:N──> Bookings
Users ──1:1──> MembershipTiers (membership_tier_id FK)
Users ──1:N──> RefreshTokens

Cinemas ──1:N──> Rooms
Cinemas ──1:N──> Snacks

Rooms ──1:N──> Seats
Rooms ──1:N──> Showtimes

Movies ──1:N──> Showtimes

Showtimes ──1:N──> ShowtimeSeats
Showtimes ──1:N──> SeatLocks
Showtimes ──1:N──> ShowtimeTicketTypes

Seats ──1:N──> ShowtimeSeats

ShowtimeSeats ──1:N──> SeatLockSeats
ShowtimeSeats ──1:N──> BookingSeats

TicketTypes ──1:N──> ShowtimeTicketTypes
TicketTypes ──1:N──> SeatLockSeats
TicketTypes ──1:N──> BookingSeats

SeatLocks ──1:N──> SeatLockSeats

Bookings ──1:N──> BookingSeats
Bookings ──1:N──> BookingSnacks
Bookings ──1:N──> BookingPromotions
Bookings ──1:1──> Payments

Payments ──1:N──> Refunds

Promotions ──1:N──> BookingPromotions
```

---

## 2. Chi tiết từng bảng

### 2.1 `users`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `user_id` | UUID | PK | |
| `username` | VARCHAR | NOT NULL | Display name |
| `email` | VARCHAR | UNIQUE, NOT NULL | |
| `phoneNumber` | VARCHAR | NULLABLE | |
| `password` | VARCHAR | NULLABLE | Null khi OAuth2 login |
| `provider` | VARCHAR | NULLABLE | `local`, `google`, `facebook` |
| `role` | ENUM | DEFAULT 'USER' | `ADMIN`, `USER`, `GUEST` |
| `avatar_url` | VARCHAR | NULLABLE | Cloudinary URL |
| `avatar_cloudinary_id` | VARCHAR | NULLABLE | |
| `loyalty_points` | INT | DEFAULT 0 | Điểm tích lũy |
| `membership_tier_id` | UUID | NULLABLE, FK | → `membership_tiers` |
| `created_at` | DATETIME | | |
| `updated_at` | DATETIME | | |

### 2.2 `membership_tiers`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `membership_tier_id` | UUID | PK | |
| `name` | VARCHAR | NOT NULL | SILVER, GOLD, PLATINUM |
| `min_points` | INT | NOT NULL | Điểm tối thiểu |
| `discount_type` | ENUM | NULLABLE | `PERCENTAGE`, `FIXED_AMOUNT` |
| `discount_value` | DECIMAL(10,2) | NULLABLE | |
| `description` | VARCHAR | NULLABLE | |
| `is_active` | BOOLEAN | DEFAULT true | |

### 2.3 `cinemas`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `cinema_id` | UUID | PK | |
| `name` | VARCHAR | NOT NULL | |
| `address` | VARCHAR | NOT NULL | |
| `hotline` | VARCHAR | NOT NULL | |
| `status` | VARCHAR(30) | DEFAULT 'ACTIVE' | |
| `is_active` | BOOLEAN | DEFAULT true | Thêm bởi migration sau |

### 2.4 `rooms`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `room_id` | UUID | PK | |
| `cinema_id` | UUID | FK → cinemas | CASCADE delete |
| `room_number` | INT | NOT NULL | Phòng 1, 2, 3... |
| `room_type` | VARCHAR | NOT NULL | STANDARD, IMAX, 3D |
| `capacity` | INT | NULLABLE | Số ghế |
| `is_active` | BOOLEAN | DEFAULT true | |

### 2.5 `movies`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `movie_id` | CHAR(36) | PK | UUID |
| `title` | VARCHAR | NOT NULL | |
| `genre` | VARCHAR | NULLABLE | "Hài, Gia đình" |
| `description` | TEXT | NULLABLE | |
| `duration` | INT | NOT NULL | Phút |
| `minimum_age` | INT | NULLABLE | |
| `director` | VARCHAR | NULLABLE | |
| `actors` | TEXT | NULLABLE | |
| `poster_url` | VARCHAR | NULLABLE | |
| `poster_cloudinary_id` | VARCHAR | NULLABLE | |
| `trailer_url` | VARCHAR | NULLABLE | YouTube URL |
| `status` | ENUM | DEFAULT 'UPCOMING' | `SHOWING`, `UPCOMING` |
| `language` | VARCHAR | NULLABLE | EN, VI |

### 2.6 `showtimes`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `showtime_id` | UUID | PK | |
| `room_id` | UUID | FK → rooms | CASCADE delete |
| `movie_id` | UUID | FK → movies | CASCADE delete |
| `format` | VARCHAR | NOT NULL | 2D, 3D, IMAX |
| `start_time` | DATETIME | NOT NULL | |

### 2.7 `seats`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `seat_id` | UUID | PK | |
| `room_id` | UUID | FK → rooms | CASCADE delete |
| `row_label` | VARCHAR(10) | NOT NULL | A, B, C... |
| `seat_number` | INT | NOT NULL | 1, 2, 3... |
| `seat_type` | ENUM | DEFAULT 'NORMAL' | `NORMAL`, `VIP`, `COUPLE` |

### 2.8 `showtime_seats`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `showtime_seat_id` | UUID | PK | |
| `seat_id` | UUID | FK → seats | CASCADE delete |
| `showtime_id` | UUID | FK → showtimes | CASCADE delete |
| `seat_status` | ENUM | DEFAULT 'AVAILABLE' | `AVAILABLE`, `LOCKED`, `BOOKED` |
| `price` | DECIMAL(10,2) | NULLABLE | Giá đã tính modifiers |
| `price_breakdown` | TEXT | NULLABLE | JSON breakdown |
| **Index** | `(showtime_id, seat_id)` | | |

### 2.9 `ticket_types`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `code` | VARCHAR | UNIQUE | ADULT, CHILD, SENIOR |
| `label` | VARCHAR | NOT NULL | "Vé Người Lớn" |
| `base_price` | DECIMAL(10,2) | NOT NULL | |
| `is_active` | BOOLEAN | DEFAULT true | |

### 2.10 `showtime_ticket_types`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `showtime_id` | UUID | FK → showtimes | |
| `ticket_type_id` | UUID | FK → ticket_types | |
| `sort_order` | INT | DEFAULT 0 | Thứ tự hiển thị |

### 2.11 `seat_locks`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `seat_lock_id` | UUID | PK | |
| `lock_owner_id` | UUID | NOT NULL | User ID hoặc Guest Session ID |
| `lock_owner_type` | ENUM | NOT NULL | `USER`, `GUEST_SESSION` |
| `user_id` | UUID | NULLABLE, FK → users | |
| `showtime_id` | UUID | FK → showtimes | CASCADE delete |
| `lock_key` | VARCHAR(100) | NOT NULL | UUID token |
| `created_at` | TIMESTAMP | | |
| `expires_at` | TIMESTAMP | NOT NULL | TTL = 10 phút (config) |
| `active` | BOOLEAN | DEFAULT true | |
| **Index** | `(showtime_id, active)` | | |
| **Index** | `(lock_owner_type, lock_owner_id)` | | |

### 2.12 `seat_lock_seats`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `seat_lock_id` | UUID | FK → seat_locks | CASCADE delete |
| `showtime_seat_id` | UUID | FK → showtime_seats | CASCADE delete |
| `ticket_type_id` | UUID | FK → ticket_types | RESTRICT delete |
| `price` | DECIMAL(10,2) | NULLABLE | Giá tại thời điểm lock |

### 2.13 `price_base`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `name` | VARCHAR | NOT NULL | "Base Ticket" |
| `base_price` | DECIMAL(10,2) | NOT NULL | |
| `is_active` | BOOLEAN | DEFAULT true | |

### 2.14 `price_modifiers`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `name` | VARCHAR | NOT NULL | Tên modifier |
| `condition_type` | VARCHAR | NOT NULL | `DAY_OF_WEEK`, `SEAT_TYPE`, `SHOWTIME_FORMAT` |
| `condition_value` | VARCHAR | NOT NULL | `WEEKEND`, `VIP`, `3D` |
| `modifier_type` | VARCHAR(50) | NOT NULL | `PERCENTAGE`, `FIXED_AMOUNT` |
| `modifier_value` | DECIMAL(10,2) | NOT NULL | |
| `is_active` | BOOLEAN | DEFAULT true | |

### 2.15 `promotions`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `promotion_id` | UUID | PK | |
| `code` | VARCHAR | UNIQUE | Mã khuyến mãi |
| `name` | VARCHAR | NOT NULL | |
| `description` | TEXT | NULLABLE | |
| `discount_type` | ENUM | NOT NULL | `PERCENTAGE`, `FIXED_AMOUNT` |
| `discount_value` | DECIMAL(10,2) | NOT NULL | |
| `start_date` | DATETIME | NOT NULL | |
| `end_date` | DATETIME | NOT NULL | |
| `usage_limit` | INT | NULLABLE | Tổng số lần dùng |
| `per_user_limit` | INT | NULLABLE | Giới hạn mỗi user |
| `is_active` | BOOLEAN | DEFAULT true | |

### 2.16 `snacks`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `snack_id` | UUID | PK | |
| `cinema_id` | UUID | FK → cinemas | CASCADE delete |
| `name` | VARCHAR | NOT NULL | |
| `type` | VARCHAR(50) | NOT NULL | popcorn, drink, combo |
| `description` | TEXT | NULLABLE | |
| `price` | DECIMAL(10,2) | NOT NULL | |
| `image_url` | VARCHAR | NULLABLE | |
| `image_cloudinary_id` | VARCHAR | NULLABLE | |

### 2.17 `bookings`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `booking_id` | UUID | PK | |
| `user_id` | UUID | NULLABLE, FK → users | Null = guest booking |
| `showtime_id` | UUID | FK → showtimes | CASCADE delete |
| `booked_at` | DATETIME | NOT NULL | |
| `total_price` | DECIMAL(10,2) | DEFAULT 0 | Giá trước giảm |
| `discountReason` | VARCHAR | NULLABLE | |
| `discountValue` | DECIMAL(10,2) | NULLABLE | |
| `finalPrice` | DECIMAL(10,2) | DEFAULT 0 | Giá cuối |
| `status` | ENUM | DEFAULT 'PENDING_PAYMENT' | Xem enum bên dưới |
| `qr_code` | VARCHAR | NULLABLE | URL QR code |
| `qr_payload` | TEXT | NULLABLE | |
| `payment_expires_at` | DATETIME | NULLABLE | Hết hạn thanh toán |
| `loyalty_points_awarded` | BOOLEAN | DEFAULT false | |
| `refunded` | BOOLEAN | DEFAULT false | |
| `refunded_at` | DATETIME | NULLABLE | |
| `refund_reason` | VARCHAR | NULLABLE | |
| **Index** | `(showtime_id, status)` | | |

**Booking Status**: `PENDING_PAYMENT` → `CONFIRMED` | `CANCELLED` | `EXPIRED` | `REFUND_PENDING` | `REFUNDED`

### 2.18 `booking_seats`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `booking_id` | UUID | FK → bookings | CASCADE delete |
| `showtime_seat_id` | UUID | FK → showtime_seats | CASCADE delete |
| `seat_lock_seat_id` | UUID | FK → seat_lock_seats | CASCADE delete |
| `ticket_type_id` | UUID | FK → ticket_types | RESTRICT delete |
| `price` | DECIMAL(10,2) | NOT NULL | Giá tại booking |

### 2.19 `booking_snacks`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `booking_id` | UUID | FK → bookings | CASCADE delete |
| `snack_id` | UUID | FK → snacks | CASCADE delete |
| `quantity` | INT | NOT NULL | |

### 2.20 `booking_promotions`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `booking_id` | UUID | FK → bookings | CASCADE delete |
| `promotion_id` | UUID | FK → promotions | CASCADE delete |
| `applied_at` | DATETIME | NOT NULL | |

### 2.21 `payments`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `payment_id` | UUID | PK | |
| `booking_id` | UUID | UNIQUE FK → bookings | 1:1 relation, CASCADE delete |
| `transaction_id` | VARCHAR | NULLABLE | Gateway transaction ID |
| `amount` | DECIMAL(10,2) | NOT NULL | Số tiền VND |
| `currency` | VARCHAR(10) | DEFAULT 'VND' | |
| `gateway_amount` | DECIMAL(10,2) | NULLABLE | Số tiền trên gateway (USD) |
| `gateway_currency` | VARCHAR(10) | NULLABLE | USD |
| `exchange_rate` | DECIMAL(10,4) | NULLABLE | Tỷ giá VND→USD |
| `status` | ENUM | DEFAULT 'PENDING' | Xem enum bên dưới |
| `method` | ENUM | NOT NULL | `PAYPAL`, `MOMO` |
| `payer_email` | VARCHAR | NULLABLE | |
| `error_message` | VARCHAR | NULLABLE | |
| `created_at` | DATETIME | NOT NULL | |
| `completed_at` | DATETIME | NULLABLE | |

**Payment Status**: `PENDING` → `SUCCESS` | `FAILED` | `REFUND_PENDING` | `REFUNDED` | `REFUND_FAILED`

### 2.22 `refunds`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `refund_id` | UUID | PK | |
| `payment_id` | UUID | FK → payments | CASCADE delete |
| `amount` | DECIMAL(10,2) | NOT NULL | |
| `refund_method` | VARCHAR | NOT NULL | PAYPAL, MOMO |
| `refund_gateway_txn_id` | VARCHAR | NULLABLE | |
| `reason` | VARCHAR | NULLABLE | |
| `created_at` | DATETIME | NOT NULL | |
| `refunded_at` | DATETIME | NULLABLE | |

### 2.23 `refresh_tokens`

| Column | Type | Constraints | Ghi chú |
|--------|------|-------------|---------|
| `id` | UUID | PK | |
| `user_id` | UUID | FK → users | |
| `token` | VARCHAR | UNIQUE | |
| `expires_at` | DATETIME | NOT NULL | |

---

## 3. Enums

| Enum | Values |
|------|--------|
| `BookingStatus` | `PENDING_PAYMENT`, `CONFIRMED`, `CANCELLED`, `EXPIRED` |
| `PaymentStatus` | `PENDING`, `COMPLETED`, `FAILED`, `REFUNDED`, `CANCELLED`, `REFUND_PENDING` |
| `PaymentMethod` | `PAYPAL`, `MOMO` |
| `SeatStatus` | `AVAILABLE`, `LOCKED`, `BOOKED` |
| `RefundStatus` | `PENDING`, `COMPLETED`, `FAILED` |
| `LockOwnerType` | `USER`, `GUEST_SESSION` |
| `MovieStatus` | `SHOWING`, `UPCOMING` |
| `SeatType` | `NORMAL`, `VIP`, `COUPLE` |
| `UserRole` | `ADMIN`, `USER`, `GUEST` |
| `DiscountType` | `PERCENTAGE`, `FIXED_AMOUNT` |
