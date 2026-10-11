<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>{{ $isAnonymous ? 'Phản ánh chất lượng' : 'Liên hệ mới' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f1a;font-family:'Segoe UI',Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f1a;padding:40px 16px;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">

          {{-- HEADER --}}
          <tr>
            <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);border-radius:16px 16px 0 0;padding:36px 40px;text-align:center;">
              <p style="margin:0 0 8px 0;font-size:11px;letter-spacing:4px;text-transform:uppercase;color:rgba(255,255,255,0.7);">Cineon</p>
              @if($isAnonymous)
                <h1 style="margin:0;font-size:26px;font-weight:800;color:#ffffff;letter-spacing:1px;">Phản ánh chất lượng</h1>
                <p style="margin:10px 0 0 0;font-size:13px;color:rgba(255,255,255,0.75);">Một khách hàng vừa gửi góp ý ẩn danh qua website</p>
              @else
                <h1 style="margin:0;font-size:26px;font-weight:800;color:#ffffff;letter-spacing:1px;">Liên hệ mới</h1>
                <p style="margin:10px 0 0 0;font-size:13px;color:rgba(255,255,255,0.75);">Một khách hàng vừa gửi liên hệ qua website</p>
              @endif
            </td>
          </tr>

          {{-- BODY --}}
          <tr>
            <td style="background-color:#1a1a2e;padding:32px 40px;">

              {{-- Sender info — chỉ hiện khi KHÔNG phải ẩn danh --}}
              @if(!$isAnonymous)
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                  <td style="padding-bottom:16px;">
                    <p style="margin:0 0 16px 0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#7c3aed;font-weight:700;">Thông tin người gửi</p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding:12px 16px;background-color:#0f0f1a;border-radius:10px 10px 0 0;border-bottom:1px solid #2a2a3e;">
                          <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                              <td width="40%" style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;font-weight:600;">Họ và tên</td>
                              <td style="font-size:14px;color:#f9fafb;font-weight:600;">{{ $submission->name }}</td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:12px 16px;background-color:#0f0f1a;border-radius:0 0 10px 10px;">
                          <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                              <td width="40%" style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#6b7280;font-weight:600;">Email</td>
                              <td style="font-size:14px;">
                                <a href="mailto:{{ $submission->email }}" style="color:#818cf8;text-decoration:none;font-weight:500;">{{ $submission->email }}</a>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
              @endif

              {{-- Message --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="{{ $isAnonymous ? '' : '' }}">
                <tr>
                  <td style="padding-bottom:12px;">
                    <p style="margin:0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#7c3aed;font-weight:700;">Nội dung</p>
                  </td>
                </tr>
                <tr>
                  <td style="background-color:#0f0f1a;border-left:3px solid #7c3aed;border-radius:0 10px 10px 0;padding:20px 24px;">
                    <p style="margin:0;font-size:14px;color:#e5e7eb;line-height:1.8;">{!! nl2br(e($submission->message)) !!}</p>
                  </td>
                </tr>
              </table>

              {{-- Attachment --}}
              @if($submission->attachment_url)
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
                <tr>
                  <td style="padding-bottom:10px;">
                    <p style="margin:0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#7c3aed;font-weight:700;">Ảnh minh chứng</p>
                  </td>
                </tr>
                <tr>
                  <td style="background-color:#0f0f1a;border-radius:10px;padding:12px;text-align:center;">
                    <a href="{{ $submission->attachment_url }}" target="_blank">
                      <img src="{{ $submission->attachment_url }}" alt="Ảnh minh chứng"
                        style="max-width:100%;max-height:400px;border-radius:8px;display:block;margin:0 auto;" />
                    </a>
                    <p style="margin:8px 0 0 0;font-size:11px;color:#6b7280;">
                      <a href="{{ $submission->attachment_url }}" target="_blank" style="color:#818cf8;">Xem ảnh gốc →</a>
                    </p>
                  </td>
                </tr>
              </table>
              @endif

              {{-- Reply button — chỉ hiện khi có email thật --}}
              @if(!$isAnonymous)
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                <tr>
                  <td align="center">
                    <a href="mailto:{{ $submission->email }}?subject=Re: Phản hồi từ Cineon"
                       style="display:inline-block;padding:13px 36px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;border-radius:50px;letter-spacing:1px;">
                      Trả lời ngay
                    </a>
                  </td>
                </tr>
              </table>
              @endif

            </td>
          </tr>

          {{-- FOOTER --}}
          <tr>
            <td style="background-color:#111122;border-radius:0 0 16px 16px;padding:20px 40px;text-align:center;border-top:1px solid #2a2a3e;">
              <p style="margin:0 0 4px 0;font-size:12px;color:#4b5563;">
                Gửi lúc: {{ $submission->created_at?->setTimezone('Asia/Ho_Chi_Minh')->format('H:i — d/m/Y') }}
              </p>
              <p style="margin:0;font-size:11px;color:#374151;">Cineon &mdash; Hệ thống đặt vé xem phim</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
