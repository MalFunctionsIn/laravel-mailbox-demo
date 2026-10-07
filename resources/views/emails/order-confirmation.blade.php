<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation {{ $order->number }}</title>
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
                        <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;">Thanks, {{ $order->customerName }}!</h1>
                        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#52525b;">
                            We've received your order. Your receipt is attached as a PDF, and
                            we'll email you again the moment it ships.
                        </p>

                        <p style="margin:0 0 20px;font-size:14px;">
                            <strong>Order #{{ $order->number }}</strong>
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #f4f4f5;">
                                        {{ $item['name'] }}
                                        <span style="color:#a1a1aa;">&times; {{ $item['qty'] }}</span>
                                    </td>
                                    <td align="right" style="padding:10px 0;border-bottom:1px solid #f4f4f5;white-space:nowrap;">
                                        {{ $order->money($item['qty'] * $item['price']) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td style="padding:10px 0;color:#52525b;">Subtotal</td>
                                <td align="right" style="padding:10px 0;">{{ $order->money($order->subtotal()) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:0 0 10px;color:#52525b;">Shipping</td>
                                <td align="right" style="padding:0 0 10px;">{{ $order->money($order->shipping()) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 0 0;border-top:2px solid #18181b;font-weight:700;">Total</td>
                                <td align="right" style="padding:12px 0 0;border-top:2px solid #18181b;font-weight:700;">
                                    {{ $order->money($order->total()) }}
                                </td>
                            </tr>
                        </table>

                        <h2 style="margin:28px 0 8px;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;color:#71717a;">
                            Shipping to
                        </h2>
                        <p style="margin:0;font-size:14px;line-height:1.6;color:#3f3f46;">
                            {!! nl2br(e($order->shippingAddress)) !!}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px;background:#fafafa;border-top:1px solid #e4e4e7;font-size:12px;color:#71717a;">
                        Questions? Just reply to this email and our support team will help.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
