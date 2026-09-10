<x-mail.shell :preheader="'Quầy '.$hotel->name.' đã mở. Email và mật khẩu tạm nằm trong thư này.'" title="Tài khoản Signage Desk">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="padding:36px 32px 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;letter-spacing:0.04em;color:#c9b8a0;">
                Xin chào {{ $user->name }}
            </td>
        </tr>
        <tr>
            <td style="padding:4px 32px 12px;font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1.15;color:#f4eee3;">
                Quầy đã mở.
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.55;color:#d8cfc3;">
                {{ $hotel->name }} sẵn sàng trên Signage Desk. Giữ thư này đến khi đổi mật khẩu.
            </td>
        </tr>
        <tr>
            <td style="padding:0 24px 24px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#14110e;border-radius:18px;">
                    <tr>
                        <td style="padding:22px 24px 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#8a8174;">
                            Chìa khóa
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;color:#8a8174;">
                            Email
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 16px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:16px;color:#f4eee3;">
                            {{ $user->email }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;color:#8a8174;">
                            Mật khẩu tạm
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 22px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:16px;letter-spacing:0.04em;color:#f4eee3;">
                            {{ $plainPassword }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#d8cfc3;">
                Gói {{ $planLabel }} · {{ $deviceLabel }}
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#d8cfc3;">
                {{ $pairingLabel }} Nền phòng nhận ảnh, MP4, hoặc link YouTube/Vimeo.
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 36px;">
                <a href="{{ $loginUrl }}" style="display:inline-block;background:#f4eee3;color:#1c1814;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;line-height:1;padding:16px 28px;border-radius:999px;text-decoration:none;">
                    Vào quầy
                </a>
            </td>
        </tr>
    </table>
</x-mail.shell>
