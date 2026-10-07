<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;">
                <tr>
                    <td style="background:#18181b;padding:24px 28px;">
                        <span style="color:#fafafa;font-size:18px;font-weight:700;letter-spacing:-0.01em;">ACME Store</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 20px;font-size:21px;line-height:1.3;">{{ $subjectLine }}</h1>
                        <div style="font-size:15px;line-height:1.65;color:#3f3f46;">
                            {!! nl2br(e($body)) !!}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px;background:#fafafa;border-top:1px solid #e4e4e7;font-size:12px;color:#71717a;">
                        Composed in the Mailbox for Laravel demo. This message was captured locally and never sent.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
