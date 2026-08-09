# Database và quan hệ dữ liệu

> Cập nhật theo 47 migrations và schema MySQL dev ngày 2026-08-09.

## 1. Tổng quan

| Thuộc tính | Giá trị hiện tại |
|---|---|
| Engine dev | MySQL 8.x / InnoDB |
| Database | `movie_booking_laravel` |
| Collation chính | `utf8mb4_unicode_ci` |
| Bảng trong DB dev | 35, gồm bảng `migrations` và bảng hạ tầng |
| Bảng được tạo trực tiếp bởi migration | 34 |
| Primary key nghiệp vụ | Chủ yếu UUID `char(36)`; `review_reactions.id` là bigint |

Con số “23 bảng” trong tài liệu cũ không còn đúng. Schema hiện đã bổ sung hero/contact, review ecosystem, loyalty transactions và notifications.

## 2. Danh mục 35 bảng

| Nhóm | Bảng |
|---|---|
| Identity/membership | `users`, `refresh_tokens`, `membership_tiers`, `loyalty_transactions`, `notifications` |
| Movie/community/content | `movies`, `movie_reviews`, `review_reactions`, `review_reports`, `review_moderation_logs`, `hero_slides`, `contact_submissions` |
| Cinema/showtime | `cinemas`, `rooms`, `seats`, `showtimes`, `showtime_seats`, `showtime_ticket_types` |
| Pricing/catalog | `ticket_types`, `price_base`, `price_modifiers`, `promotions`, `snacks` |
| Booking/payment | `seat_locks`, `seat_lock_seats`, `bookings`, `booking_seats`, `booking_snacks`, `booking_promotions`, `payments`, `refunds` |
| Framework/operations | `cache`, `cache_locks`, `jobs`, `migrations` |

Schema hiện không tạo các bảng `sessions`, `password_reset_tokens`, `failed_jobs` hoặc `job_batches`; đừng giả định chúng tồn tại khi đổi driver.

## 3. Quan hệ chính

```text
membership_tiers 1 ── N users 1 ── N refresh_tokens
                              ├── N loyalty_transactions
                              ├── N notifications (polymorphic)
                              ├── N bookings
                              ├── N movie_reviews
                              ├── N review_reports
                              └── N review_moderation_logs (moderator)

cinemas 1 ── N rooms 1 ── N seats
    └── N snacks       └── N showtimes N ── 1 movies
                                  ├── N showtime_seats N ── 1 seats
                                  └── N showtime_ticket_types N ── 1 ticket_types

showtimes 1 ── N seat_locks 1 ── N seat_lock_seats
showtimes 1 ── N bookings
bookings 1 ── N booking_seats
         ├── N booking_snacks
         ├── N booking_promotions
         ├── 1 payment
         └── N refunds (qua payment/booking)

movies 1 ── N movie_reviews
movie_reviews 1 ── N replies (self parent_id)
              ├── N review_reactions
              ├── N review_reports
              └── N review_moderation_logs
```

## 4. Identity và membership

### `users`

Key columns:

- `user_id` UUID PK, `username`, unique `email`, nullable `phoneNumber`;
- nullable `password` cho tài khoản OAuth, nullable `provider`;
- `role`: `ADMIN | USER | GUEST`;
- `avatar_url`, `avatar_cloudinary_id`;
- `loyalty_points`, nullable `membership_tier_id`;
- timestamps.

Tên `phoneNumber` là camelCase do schema legacy; API/resource có thể map sang camelCase tương ứng.

### `refresh_tokens`

`token_id` UUID PK, `user_id` FK cascade, `token`, nullable `revoked_at`, timestamps. Expiry được kiểm tra từ token/lifecycle; live schema không có cột `expires_at`.

### `membership_tiers`

`membership_tier_id`, `name`, `min_points`, `discount_type`, `discount_value`, `description`, `is_active`, datetime timestamps.

### `loyalty_transactions`

Lịch sử bất biến tương đối của biến động điểm:

```text
loyalty_transaction_id, user_id, type, points, balance_after,
source_type, source_id, description, expires_at, timestamps
```

Index `(user_id, created_at)` và `(source_type, source_id)` hỗ trợ history/idempotency theo nguồn.

### `notifications`

Laravel database notification: UUID `id`, `type`, polymorphic `notifiable_type/notifiable_id`, JSON/text `data`, nullable `read_at`, timestamps.

## 5. Movie, review và content

### `movies`

`movie_id`, title/genre/description, duration/minimum_age, director/actors, poster URL/public ID, trailer URL, status, language, `premiere_date`, timestamps.

Status persisted: `SHOWING | UPCOMING`.

### `movie_reviews`

```text
review_id UUID PK
parent_id nullable FK → movie_reviews (reply tree)
movie_id FK, user_id nullable FK
reviewer_name, rating 1..5, comment
is_spoiler, status, edited_at
image_url, video_url, timestamps
```

Review guest có thể giữ `reviewer_name` khi `user_id` null. Xóa movie cascade review; xóa user set null theo migration gốc.

### Review phụ trợ

| Bảng | Cột đáng chú ý |
|---|---|
| `review_reactions` | bigint `id`, `review_id`, `reaction_type`, nullable `user_id`, `ip_address` |
| `review_reports` | UUID `report_id`, review/user/IP, `reason`, `details`, `status` |
| `review_moderation_logs` | UUID, review, nullable moderator, action, previous/next status, reason |

### Hero/contact

| Bảng | Cột đáng chú ý |
|---|---|
| `hero_slides` | UUID, title, alt text, image URL/public ID, sort order, active |
| `contact_submissions` | UUID, name/email/message, attachment URL, source page, IP, user agent, notified_at |

## 6. Cinema, showtime và seats

| Bảng | Key/columns chính |
|---|---|
| `cinemas` | `cinema_id`; name, address, `city`, `district`, hotline, hero image URL/public ID, `is_active`, status |
| `rooms` | `room_id`; `cinema_id` FK, room number/type, capacity, active |
| `seats` | `seat_id`; `room_id` FK, row label, seat number, type `NORMAL/VIP/COUPLE` |
| `showtimes` | `showtime_id`; room/movie FK, format, `is_active`, start_time |
| `showtime_seats` | concrete seat/showtime pair, status, calculated price và breakdown |
| `showtime_ticket_types` | showtime/ticket type pivot, active, sort order |

`showtime_seats` có composite index `(showtime_id, seat_id)` để phục vụ truy vấn; migration hiện chưa đặt unique constraint cho cặp này, nên service phải tránh tạo concrete seat trùng. Trạng thái: `AVAILABLE | LOCKED | BOOKED`.

## 7. Pricing và catalog

| Bảng | Cột chính |
|---|---|
| `price_base` | UUID, name, base_price, active |
| `price_modifiers` | condition type/value, modifier type/value, active |
| `ticket_types` | UUID, unique code, label, modifier type/value, active, sort order |
| `promotions` | unique code, discount type/value, start/end, usage/per-user limit, active |
| `snacks` | cinema FK, name/type/description/price, image URL/public ID |

Ticket type hiện dùng `modifier_type` + `modifier_value`, không có `base_price` như tài liệu cũ.

## 8. Seat locks và booking snapshot

### `seat_locks`

```text
seat_lock_id UUID PK
lock_owner_id + lock_owner_type(USER|GUEST_SESSION)
nullable user_id, showtime_id, lock_key
created_at, expires_at, active
```

### `seat_lock_seats`

Pivot lock–showtime seat–ticket type và giữ `price` snapshot.

### `bookings`

Key fields:

- `booking_id`, nullable `user_id`, `showtime_id`, `booked_at`;
- `total_price`, `discount_reason`, `discount_value`, `final_price`;
- persisted status `PENDING_PAYMENT | CONFIRMED | CANCELLED | EXPIRED`;
- `qr_code`, `qr_payload`, `payment_expires_at`;
- `loyalty_points_awarded`, refund flags/time/reason;
- timestamps.

Refund pending/refunded được biểu diễn chủ yếu qua payment/refund và được transformer map vào booking response; không nằm trong enum DB của `bookings.status` hiện tại.

### Booking child tables

| Bảng | Snapshot |
|---|---|
| `booking_seats` | showtime seat, nullable source lock seat, ticket type, price |
| `booking_snacks` | snack + quantity |
| `booking_promotions` | promotion + `discount_amount` + applied_at |

## 9. Payments và refunds

### `payments`

`payment_id` UUID; unique `booking_id` (quan hệ 1:1); nullable `user_id`; gateway identifiers `order_id`, `txn_ref`, `transaction_id`; URL/JSON response; amount/currency và gateway amount/currency/exchange rate; status/method; paid/completed/error metadata.

| Field | Values |
|---|---|
| `method` | `PAYPAL`, `MOMO` |
| `status` | `PENDING`, `COMPLETED`, `FAILED`, `CANCELLED`, `REFUND_PENDING`, `REFUNDED` |

`txn_ref` và `booking_id` có unique index, hỗ trợ idempotency một payment active record trên mỗi booking theo schema hiện tại.

### `refunds`

`refund_id`, `payment_id`, `booking_id`, `user_id`, status `PENDING|COMPLETED|FAILED`, amount/currency, gateway method/transaction/response, reason và timestamps nghiệp vụ.

## 10. Integrity và quy tắc migration

- Mọi thay đổi schema phải đi qua migration mới; không sửa migration đã chạy ở môi trường dùng chung.
- Monetary values dùng `decimal`, không dùng float trong DB.
- UUID sinh ở application/model; luôn đặt FK/index cho đường query chính.
- Xóa cascade chỉ dùng khi child không còn ý nghĩa độc lập; user ở review/report nên set null nếu cần giữ audit.
- Booking/payment/review moderation cần transaction và idempotency ở service, không chỉ dựa FK.
- Sau migration, kiểm tra bằng:

```powershell
php artisan migrate:status
php artisan db:show --counts
php artisan test
```
