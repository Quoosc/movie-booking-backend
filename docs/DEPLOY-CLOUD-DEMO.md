# Deploy Cineon miễn phí trên cloud — dự kiến hủy VPS ngày 20/10/2026

Frontend: https://cineon-cinema.vercel.app/. Backend chạy độc lập trên Ubuntu VPS,
không phụ thuộc máy Windows có bật hay không. Bộ triển khai dùng PHP-FPM, Nginx,
MySQL 8.4, Redis, queue worker, scheduler và Caddy tự cấp HTTPS.

## Trạng thái triển khai ngày 12/10/2026

Backend đã chạy trên VPS `161.33.81.211`, Ubuntu 24.04 x86_64, khoảng 1 GB RAM.
Đã thêm swap 2 GB, giảm buffer pool/connection của MySQL và dùng PHP-FPM ondemand
với tối đa 3 worker. Image app/web được build trên Windows Docker rồi nạp vào VPS
để tránh build nặng trên máy 1 GB RAM. Dữ liệu đã import vào MySQL cloud; các
dịch vụ có restart policy và Docker khởi động cùng hệ thống.

- API: `https://cineon.161.33.81.211.sslip.io/api`.
- Health: `https://cineon.161.33.81.211.sslip.io/up`.
- HTTPS, API phim/rạp, CORS Vercel và đăng nhập user/admin đã kiểm tra thành công.
- Ba variable Production đã được người dùng lưu trong Vercel theo ảnh xác nhận:
  `VITE_API_BASE_URL` là API ở trên, `VITE_USE_MOCK=false`, `VITE_AUTH_MODE=bearer`.
  Cần Redeploy và kiểm tra bản mới; chưa xác nhận deployment mới đã Ready.
- Google OAuth cần thêm redirect URI
  `https://cineon.161.33.81.211.sslip.io/login/oauth2/code/google` trong Google Console.

Để redeploy khi đã có image build sẵn, dùng `bash scripts/deploy-cloud.sh --no-build`.
Gói zip cũ được giữ tại `.demo-deploy/cineon-cloud-demo-before-vps.zip`; gói mới
chứa cấu hình dùng ít RAM và script chuẩn bị Ubuntu. Source/API đã deploy không
đồng nghĩa môi trường Vercel đã được cập nhật.

## 1. Tạo máy chủ miễn phí

Đăng ký Oracle Cloud và tạo Compute instance **Always Free eligible** trong home
region. Chọn Ubuntu 24.04, Ampere A1, 2 OCPU và 6 GB RAM, boot volume 50 GB;
giữ tổng tài nguyên trong hạn mức Always Free hiển thị trên tài khoản. Lưu SSH
private key và public IPv4. Việc đăng ký/xác minh và tình trạng còn máy trống do
Oracle quyết định; bộ source này không tự tạo tài khoản hoặc VM.

Trong Security List hoặc Network Security Group của subnet, mở inbound TCP 80
và 443 từ Internet; TCP 22 cho địa chỉ IP của bạn. Không mở 3306 hoặc 6379.
Nếu VM có firewall riêng, cho phép 80/443 tại đó. Không tắt toàn bộ firewall.

Nguồn: https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm
và https://docs.oracle.com/en-us/iaas/Content/Compute/tutorials/first-linux-instance/overview.htm.

## 2. Upload gói đã chuẩn bị

Gói private nằm tại `.demo-deploy/cineon-cloud-demo.zip` trong backend trên
Windows. ZIP chứa source mới nhất, SQL snapshot và các credential sandbox/OAuth
đang được cấu hình. **Không upload ZIP, SQL hoặc demo-provider.env lên GitHub.**
Source build không chứa `.env` local, vendor, node_modules hoặc các bản dump.

Chạy PowerShell, thay đường dẫn key và `SERVER_IP` bằng giá trị của VM:

```powershell
ssh -i "C:\path\oracle.key" ubuntu@SERVER_IP
```

Trong SSH, cài Docker từ gói Ubuntu và tạo thư mục:

```bash
sudo apt-get update
sudo apt-get install -y docker.io docker-compose-v2 unzip
sudo systemctl enable --now docker
sudo usermod -aG docker ubuntu
mkdir -p /home/ubuntu/cineon
chmod 700 /home/ubuntu/cineon
exit
```

Đăng nhập SSH lại để quyền nhóm Docker có hiệu lực. Tại PowerShell:

```powershell
scp -i "C:\path\oracle.key" "D:\laragon\www\movie-booking-backend-laravel\.demo-deploy\cineon-cloud-demo.zip" ubuntu@SERVER_IP:/home/ubuntu/cineon/
ssh -i "C:\path\oracle.key" ubuntu@SERVER_IP
```

Trong SSH:

```bash
cd /home/ubuntu/cineon
unzip cineon-cloud-demo.zip
chmod 600 demo-data.sql demo-provider.env
docker compose version
```

## 3. Tạo URL API và khởi chạy

Có domain riêng: tạo DNS A record trỏ tới public IP của VPS. Nếu chưa có domain,
có thể dùng `cineon.SERVER_IP.sslip.io`, ví dụ `cineon.203.0.113.10.sslip.io` (IP
ví dụ, không được dùng nguyên giá trị này). sslip.io ánh xạ hostname về IP trong
tên; phụ thuộc dịch vụ DNS bên ngoài. Kiểm tra hostname phân giải đúng trước khi
deploy. Caddy cần truy cập được cổng 80/443 để cấp certificate.

Nguồn: https://sslip.io/ và https://caddyserver.com/docs/quick-starts/https.

Thay `API_HOST` và email nhận thông báo certificate:

```bash
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/work -w /work php:8.3-cli php scripts/cloud-env.php API_HOST YOUR_EMAIL
bash scripts/deploy-cloud.sh
docker compose --env-file .env.cloud -f compose.cloud.yaml ps
docker compose --env-file .env.cloud -f compose.cloud.yaml exec -T app php scripts/check-demo.php --redis
curl --fail https://API_HOST/up
```

Script sinh APP_KEY, JWT_SECRET và mật khẩu MySQL ngẫu nhiên, ổn định trong
`.env.cloud`, nhập credential sandbox/OAuth từ file private nếu có. Không sinh
lại keys khi redeploy. Nếu cần đổi URL/credential, chỉnh `.env.cloud` và chạy lại
`bash scripts/deploy-cloud.sh`.

SQL được import **chỉ khi database chưa có bảng**. Migration chạy một lần trước
khi app/worker/scheduler khởi động. Nếu import thất bại giữa chừng, script dừng;
không tự xóa database để thử lại. Worker và scheduler tự khởi động lại sau khi
container/VM restart. Khi redeploy, dữ liệu nằm trong Docker volumes được giữ.
Upload public local, nếu có, được nạp vào volume storage. Migrations/cleanup chỉ
chạy trên database cloud, không ảnh hưởng database Laragon.

Nếu HTTPS chưa lên, xem log:

```bash
docker compose --env-file .env.cloud -f compose.cloud.yaml logs --tail=100 caddy nginx app
```

## 4. Nối frontend Vercel

Vercel → project Cineon → Settings → Environment Variables → Production:

| Variable | Value |
| --- | --- |
| VITE_API_BASE_URL | `https://API_HOST/api` |
| VITE_USE_MOCK | `false` |
| VITE_AUTH_MODE | `bearer` |

Giữ các biến Cloudinary/contact hiện có. Nếu upload ảnh admin chưa được cấu
hình, lấy `VITE_CLOUDINARY_CLOUD_NAME`, `VITE_CLOUDINARY_UPLOAD_PRESET` và
`VITE_CLOUDINARY_FOLDER` từ `.env.laravel` local. Không đặt secret PayPal, MoMo,
Google hoặc database vào biến `VITE_*`.

Deployments → bản mới nhất → Redeploy để build với biến mới. Không dùng lại build
cache nếu bản build vẫn giữ URL API cũ. `.env.laravel` local không tự được dùng
bởi lệnh build production trên Vercel. Backend đã cho phép CORS đúng origin
`https://cineon-cinema.vercel.app` và bật secure cookies/SameSite=None cho HTTPS.
Trình duyệt chặn third-party cookies có thể ảnh hưởng OAuth/refresh giữa hai
domain; email/password dùng bearer token vẫn là luồng cần kiểm tra trước.

Nguồn: https://vercel.com/docs/environment-variables.

## 5. Google và thanh toán demo

Google Cloud Console → OAuth client hiện có → Authorized redirect URIs:
`https://API_HOST/login/oauth2/code/google`. Lưu thêm URI mới, giữ localhost nếu
còn phát triển local. Nếu OAuth consent ở Testing, tài khoản demo cần nằm trong
Test users. Đăng nhập email/password không cần bước Google này.

PayPal/MoMo vẫn là **sandbox**, không chuyển sang live. Bộ cấu hình dùng:

- PayPal return: `https://cineon-cinema.vercel.app/payment-callback?method=PAYPAL`.
- PayPal cancel: `https://cineon-cinema.vercel.app/checkout?paymentCancelled=1`.
- MoMo return: `https://cineon-cinema.vercel.app/payment-callback?method=MOMO`.
- MoMo IPN: `https://API_HOST/api/payments/momo/ipn`.

Các return URL cũ `/payments/paypal/return` và `/momo/return` không có route
backend tương ứng, nên bản cloud đưa browser về trang callback có thật ở frontend.
MoMo chỉ xác nhận thanh toán sau IPN hợp lệ; không giả lập thành công từ redirect.
Credential có trong bundle không chứng minh provider sandbox còn hoạt động;
phải kiểm tra giao dịch sau khi có public URL. Email đang dùng log driver và
contact notification tắt; muốn gửi mail thật phải cấu hình SMTP riêng.

## 6. Kiểm tra bản online

Tại PowerShell trong thư mục backend:

```powershell
.\scripts\smoke-cloud.ps1 -ApiUrl "https://API_HOST"
```

Script kiểm tra HTTPS `/up`, phim, rạp và CORS cho origin Vercel, bao gồm header
guest checkout. Trên frontend kiểm tra thủ công: danh sách phim, chi tiết phim,
lịch chiếu 12–16/10, login user/admin, chọn/giữ ghế, bắp nước, thanh toán sandbox
và QR vé. Không chạy E2E.

Snapshot hiện có 16 phim, 3 rạp, 33 món bắp nước, 97 suất chiếu tổng cộng; mỗi
ngày 12–16/10 có 16 suất active. Có dữ liệu cũ ngoài giai đoạn demo; giữ nguyên.
Backend public đã được kiểm tra; các luồng qua bản frontend Vercel mới và giao
dịch sandbox vẫn cần xác nhận sau Redeploy.

## Hủy VPS ngày 20/10

Theo yêu cầu mới nhất, dự kiến hủy VPS ngày **20/10/2026**, giờ Việt Nam. Chưa
cài lịch tự tắt hoặc tự xóa. Lịch chiếu hiện chỉ có đến 16/10; giữ máy chủ đến
20/10 không tự tạo thêm lịch chiếu. Trước khi hủy, backup dữ liệu cần giữ. Có thể
dừng container bằng:

```bash
docker compose --env-file .env.cloud -f compose.cloud.yaml stop
```

Lệnh stop giữ volumes và dữ liệu. Nếu muốn xóa tài nguyên cloud, hãy backup trước
và tự kiểm tra boot volume; không dùng `down -v` khi chưa có backup cần giữ.

## Tạo lại bundle khi dữ liệu/code thay đổi

Di chuyển bundle/snapshot/provider.env cũ khỏi `.demo-deploy` trước khi tạo mới;
script không tự ghi đè. Chạy tại backend Windows:

```powershell
php scripts/export-demo.php "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe"
php scripts/export-provider-demo.php
.\scripts\package-demo.ps1
```
