@props(['preheader' => '', 'title' => 'Signage Desk'])
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? 'Signage Desk' }}</title>
</head>
<body style="margin:0;padding:0;background:#14110e;color:#f4eee3;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $preheader }}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#14110e;">
        <tr>
            <td align="center" style="padding:32px 16px 48px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:8px 8px 28px;font-family:Georgia,'Times New Roman',serif;font-size:11px;letter-spacing:0.28em;text-transform:uppercase;color:#c9b8a0;">
                            Signage Desk
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0;background:#1c1814;border:1px solid #3a322b;border-radius:24px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 8px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#8a8174;">
                            Signage Desk gửi thư này vì địa chỉ được dùng để mở quầy. Nếu không phải bạn, bỏ qua là được.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
