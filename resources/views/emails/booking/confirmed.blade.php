<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Xác nhận đặt vé – Cineon</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f1a;font-family:'Segoe UI',Arial,sans-serif;">

@php
  $showtime  = $booking->showtime;
  $movie     = $showtime?->movie;
  $room      = $showtime?->room;
  $cinema    = $room?->cinema;
  $user      = $booking->user;

  $movieTitle   = $movie?->title    ?? '—';
  $cinemaName   = $cinema?->name    ?? '—';
  $cinemaAddr   = $cinema?->address ?? '—';
  $roomName     = $room   ? 'Phòng ' . $room->room_number : '—';
  $startTime    = $showtime?->start_time
                    ? $showtime->start_time->setTimezone('Asia/Ho_Chi_Minh')
                              ->format('H:i – l, d/m/Y')
                    : '—';

  // Ghế
  $seats = $booking->bookingSeats->map(function($bs) {
      $seat = $bs->showtimeSeat?->seat;
      return $seat ? ($seat->row_label . str_pad($seat->seat_number, 2, '0', STR_PAD_LEFT)) : '?';
  })->join(', ');

  // Bắp nước
  $snacks      = $booking->bookingSnacks;
  $hasSnacks   = $snacks->isNotEmpty();
  $snackTotal  = $snacks->sum(fn($bs) => ($bs->snack?->price ?? 0) * $bs->quantity);

  // Giá
  $totalPrice  = number_format((float)$booking->total_price,  0, ',', '.');
  $discount    = number_format((float)$booking->discount_value, 0, ',', '.');
  $finalPrice  = number_format((float)$booking->final_price,  0, ',', '.');

  $bookingCode = strtoupper(substr($booking->booking_id, 0, 8));

  // QR code image qua Google Charts API (không cần package thêm)
  $qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=280x280&choe=UTF-8&chl='
         . urlencode($booking->qr_payload ?? $booking->booking_id);
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f1a;padding:40px 16px;">
  <tr>
    <td align="center">
      <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">

        {{-- HEADER --}}
        <tr>
          <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;">
            <p style="margin:0 0 6px 0;font-size:11px;letter-spacing:4px;text-transform:uppercase;color:rgba(255,255,255,0.7);">Cineon</p>
            <h1 style="margin:0;font-size:24px;font-weight:800;color:#ffffff;">🎬 Đặt vé thành công!</h1>
            <p style="margin:8px 0 0 0;font-size:13px;color:rgba(255,255,255,0.8);">
              Vé của bạn đã được xác nhận. Mang QR code đến rạp để check-in.
            </p>
          </td>
        </tr>

        {{-- QR + INFO --}}
        <tr>
          <td style="background-color:#1a1a2e;padding:32px 40px;">

            {{-- QR CODE --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
              <tr>
                <td align="center">
                  <div style="display:inline-block;background:#ffffff;border-radius:16px;padding:16px;box-shadow:0 8px 32px rgba(0,0,0,0.5);">
                    <img src="{{ $qrUrl }}" alt="QR Code vé" width="200" height="200"
                         style="display:block;border-radius:8px;" />
                  </div>
                  <p style="margin:10px 0 0 0;font-size:12px;color:#6b7280;letter-spacing:2px;">
                    MÃ ĐẶT VÉ: <strong style="color:#e5e7eb;letter-spacing:3px;">{{ $bookingCode }}</strong>
                  </p>
                </td>
              </tr>
            </table>

            {{-- MOVIE INFO --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
              <tr>
                <td style="padding-bottom:12px;">
                  <p style="margin:0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#7c3aed;font-weight:700;">Thông tin phim</p>
                </td>
              </tr>
              <tr>
                <td style="background-color:#0f0f1a;border-radius:12px;padding:0;overflow:hidden;">

                  {{-- Tên phim --}}
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding:12px 16px;border-bottom:1px solid #2a2a3e;">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Phim</p>
                        <p style="margin:4px 0 0 0;font-size:16px;font-weight:800;color:#f9fafb;">{{ $movieTitle }}</p>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:12px 16px;border-bottom:1px solid #2a2a3e;">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Rạp chiếu</p>
                        <p style="margin:4px 0 0 0;font-size:14px;font-weight:600;color:#f9fafb;">{{ $cinemaName }}</p>
                        <p style="margin:2px 0 0 0;font-size:12px;color:#9ca3af;">{{ $cinemaAddr }}</p>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:12px 16px;border-bottom:1px solid #2a2a3e;">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Suất chiếu</p>
                        <p style="margin:4px 0 0 0;font-size:14px;font-weight:600;color:#f9fafb;">{{ $startTime }}</p>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:12px 16px;border-bottom:1px solid #2a2a3e;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                          <tr>
                            <td width="50%">
                              <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Phòng chiếu</p>
                              <p style="margin:4px 0 0 0;font-size:14px;font-weight:600;color:#f9fafb;">{{ $roomName }}</p>
                            </td>
                            <td width="50%">
                              <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Số vé</p>
                              <p style="margin:4px 0 0 0;font-size:14px;font-weight:600;color:#f9fafb;">{{ $booking->bookingSeats->count() }}</p>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:12px 16px;{{ $hasSnacks ? 'border-bottom:1px solid #2a2a3e;' : '' }}">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Ghế ngồi</p>
                        <p style="margin:4px 0 0 0;font-size:14px;font-weight:600;color:#f9fafb;">{{ $seats ?: '—' }}</p>
                      </td>
                    </tr>

                    {{-- Bắp nước (nếu có) --}}
                    @if($hasSnacks)
                    <tr>
                      <td style="padding:12px 16px;">
                        <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;">Bắp nước / Combo</p>
                        @foreach($snacks as $bs)
                        <p style="margin:4px 0 0 0;font-size:13px;color:#d1d5db;">
                          {{ $bs->snack?->name ?? '—' }}
                          &times; {{ $bs->quantity }}
                          @if($bs->snack?->price)
                            <span style="color:#a78bfa;">&nbsp;{{ number_format($bs->snack->price * $bs->quantity, 0, ',', '.') }}đ</span>
                          @endif
                        </p>
                        @endforeach
                      </td>
                    </tr>
                    @endif
                  </table>

                </td>
              </tr>
            </table>

            {{-- TỔNG TIỀN --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f1a;border-radius:12px;padding:16px;margin-bottom:24px;">
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #2a2a3e;">
                  <table width="100%"><tr>
                    <td style="font-size:13px;color:#9ca3af;">Tạm tính</td>
                    <td align="right" style="font-size:13px;color:#d1d5db;">{{ $totalPrice }}đ</td>
                  </tr></table>
                </td>
              </tr>
              @if((float)$booking->discount_value > 0)
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #2a2a3e;">
                  <table width="100%"><tr>
                    <td style="font-size:13px;color:#9ca3af;">Giảm giá</td>
                    <td align="right" style="font-size:13px;color:#4ade80;">-{{ $discount }}đ</td>
                  </tr></table>
                </td>
              </tr>
              @endif
              <tr>
                <td style="padding:12px 0 0 0;">
                  <table width="100%"><tr>
                    <td style="font-size:14px;font-weight:700;color:#ffffff;">Tổng thanh toán</td>
                    <td align="right" style="font-size:20px;font-weight:800;color:#facc15;">{{ $finalPrice }}đ</td>
                  </tr></table>
                </td>
              </tr>
            </table>

            {{-- NOTE --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1e1b4b;border-radius:12px;padding:14px 16px;margin-bottom:24px;border:1px solid #3730a3;">
              <tr>
                <td style="font-size:12px;color:#c7d2fe;line-height:1.7;">
                  📌 <strong style="color:#a5b4fc;">Lưu ý:</strong> Vui lòng xuất trình QR code này tại quầy hoặc cổng soát vé khi đến rạp.<br/>
                  QR code chỉ có hiệu lực 1 lần và dành riêng cho suất chiếu này.
                </td>
              </tr>
            </table>

          </td>
        </tr>

        {{-- FOOTER --}}
        <tr>
          <td style="background-color:#111122;border-radius:0 0 16px 16px;padding:20px 40px;text-align:center;border-top:1px solid #2a2a3e;">
            <p style="margin:0 0 4px 0;font-size:12px;color:#4b5563;">
              Đặt lúc: {{ $booking->booked_at?->setTimezone('Asia/Ho_Chi_Minh')->format('H:i — d/m/Y') }}
            </p>
            <p style="margin:0;font-size:11px;color:#374151;">
              Cineon &mdash; Hệ thống đặt vé xem phim &mdash;
              <a href="mailto:{{ config('contact.receiver_email') }}" style="color:#6b7280;text-decoration:none;">Liên hệ hỗ trợ</a>
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
