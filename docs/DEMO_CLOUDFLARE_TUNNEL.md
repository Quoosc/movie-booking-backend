# Cloudflare Tunnel Demo Guide

> Cập nhật ngày 2026-08-09 cho Laravel backend hiện tại.

## 1. Khi nào nên dùng

Tunnel phù hợp để demo ngắn hạn từ máy local. Quick Tunnel không phù hợp cho production hoặc demo payment/OAuth ổn định vì hostname đổi sau khi restart.

Backend có hai cách chạy phổ biến:

| Cách chạy | Origin local |
|---|---|
| `php artisan serve --port=8000` | `http://localhost:8000` |
| `docker compose --env-file .env.docker up -d --build` | `http://localhost:8080` (`8080:80`) |

Các ví dụ bên dưới dùng Docker port `8080`. Nếu chạy Artisan, thay bằng `8000`.

## 2. Quick Tunnel

```powershell
cloudflared tunnel --url http://localhost:8080
```

Cloudflare trả URL dạng `https://random.trycloudflare.com`. API base public là:

```text
https://random.trycloudflare.com/api
```

Quick Tunnel phù hợp để gọi API thường. Với Google OAuth, PayPal và MoMo, URL thay đổi khiến callback config nhanh lỗi; dùng named tunnel/domain ổn định cho các flow này.

## 3. Named tunnel

```powershell
cloudflared tunnel login
cloudflared tunnel create movie-booking-demo
```

Ví dụ `%USERPROFILE%\.cloudflared\config.yml`:

```yaml
tunnel: <tunnel-id>
credentials-file: C:\Users\<you>\.cloudflared\<tunnel-id>.json

ingress:
  - hostname: api.example.com
    service: http://localhost:8080
  - service: http_status:404
```

```powershell
cloudflared tunnel route dns movie-booking-demo api.example.com
cloudflared tunnel run movie-booking-demo
```

Frontend public nên dùng domain riêng, ví dụ `https://app.example.com`.

## 4. Environment cần đồng bộ

Backend demo:

```env
APP_URL=https://api.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com

FRONTEND_REDIRECT_URL=https://app.example.com/oauth2/success
GOOGLE_REDIRECT_URI=https://api.example.com/login/oauth2/code/google

PAYPAL_RETURN_URL=https://app.example.com/payment/callback?method=PAYPAL
PAYPAL_CANCEL_URL=https://app.example.com/

MOMO_RETURN_URL=https://app.example.com/payment/callback?method=MOMO
MOMO_IPN_URL=https://api.example.com/api/payments/momo/ipn
```

Frontend build:

```env
VITE_API_BASE_URL=https://api.example.com/api
```

Sau khi đổi `.env` Laravel:

```powershell
php artisan optimize:clear
```

Sau khi đổi `VITE_*`, build/deploy lại frontend vì Vite inject biến vào build output.

## 5. Cấu hình bên thứ ba

### Google

Trong Google Cloud Console, authorized redirect URI phải khớp tuyệt đối:

```text
https://api.example.com/login/oauth2/code/google
```

### PayPal

- Return URL trỏ về frontend payment callback.
- Frontend gọi backend capture cho PayPal sau redirect.
- Cancel URL nên dẫn tới một trang frontend rõ ràng.

### MoMo

- Return URL trỏ về frontend callback để hiển thị/poll trạng thái.
- IPN URL phải trỏ trực tiếp vào backend `/api/payments/momo/ipn`.
- Chỉ signed IPN xác nhận giao dịch; query của browser redirect không có thẩm quyền.

## 6. CORS và HTTPS

- `CORS_ALLOWED_ORIGINS` chỉ chứa origin, không thêm path `/api` và không để dấu `/` cuối nếu config không normalize.
- Không dùng wildcard `*` khi gửi credentials.
- Frontend và callback public phải dùng HTTPS để tránh mixed content/cookie bị chặn.
- Nếu dùng nhiều frontend origin, phân tách bằng dấu phẩy theo `config/cors.php`.

## 7. Queue và scheduler

Tunnel chỉ chuyển tiếp HTTP; nó không chạy worker. Máy demo vẫn phải giữ các process cần thiết:

```powershell
php artisan queue:work
php artisan schedule:work
```

Scheduler giải phóng lock/booking hết hạn; queue phục vụ mail/notification theo cấu hình.

## 8. Checklist trước demo

1. `GET https://api.example.com/up` trả health response.
2. `GET https://api.example.com/api/movies` trả JSON.
3. Frontend gọi đúng URL có `/api` và không lỗi CORS.
4. Login/refresh/logout hoạt động qua HTTPS.
5. Google callback domain khớp console và env.
6. PayPal return URL quay về frontend và capture thành công.
7. MoMo IPN truy cập được từ internet; frontend chỉ poll persisted status.
8. Worker/scheduler còn chạy trong suốt buổi demo.
9. Không commit `.env`, tunnel credential JSON hoặc gateway secret.

## 9. Giới hạn

- Quick Tunnel có hostname tạm thời.
- Máy local mất mạng/ngủ là demo dừng.
- Không có autoscaling, HA, central log hoặc backup production.
- Payment/OAuth cần domain ổn định và cấu hình callback chính xác.

Với demo dài ngày, nên deploy backend/database/Redis lên hạ tầng ổn định thay vì giữ tunnel local.
