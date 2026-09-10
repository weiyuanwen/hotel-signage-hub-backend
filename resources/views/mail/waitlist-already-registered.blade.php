<x-mail.shell :preheader="'Quầy Signage Desk của bạn vẫn còn. Đăng nhập bằng mật khẩu hiện tại.'" title="Đăng nhập Signage Desk">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="padding:36px 32px 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;letter-spacing:0.04em;color:#c9b8a0;">
                Xin chào {{ $user->name }}
            </td>
        </tr>
        <tr>
            <td style="padding:4px 32px 12px;font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1.15;color:#f4eee3;">
                Quầy vẫn còn.
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.55;color:#d8cfc3;">
                {{ $user->email }} đã có tài khoản Signage Desk. Vào bằng mật khẩu hiện tại, không cần mở quầy mới.
            </td>
        </tr>
        <tr>
            <td style="padding:0 32px 36px;">
                <a href="{{ $loginUrl }}" style="display:inline-block;background:#f4eee3;color:#1c1814;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;line-height:1;padding:16px 28px;border-radius:999px;text-decoration:none;">
                    Đăng nhập
                </a>
            </td>
        </tr>
    </table>
</x-mail.shell>
