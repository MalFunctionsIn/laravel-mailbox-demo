ACME STORE
==========

Thanks, {{ $order->customerName }}!

We've received your order. Your receipt is attached as a PDF, and we'll
email you again the moment it ships.

Order #{{ $order->number }}
--------------------------------
@foreach ($order->items as $item)
{{ $item['qty'] }} x {{ $item['name'] }} — {{ $order->money($item['qty'] * $item['price']) }}
@endforeach

Subtotal: {{ $order->money($order->subtotal()) }}
Shipping: {{ $order->money($order->shipping()) }}
Total:    {{ $order->money($order->total()) }}

Shipping to
-----------
{{ $order->shippingAddress }}

Questions? Just reply to this email and our support team will help.
