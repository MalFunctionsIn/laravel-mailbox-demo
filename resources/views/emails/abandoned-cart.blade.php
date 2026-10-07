<x-mail::message>
# You left something behind

Hi {{ $order->customerName }}, your cart is still waiting for you.

@foreach ($order->items as $item)
- **{{ $item['name'] }}** — {{ $order->money($item['price']) }}
@endforeach

Your cart total comes to **{{ $order->money($order->subtotal()) }}**.

<x-mail::button :url="'https://acme-store.test/cart'">
Finish checking out
</x-mail::button>

Carts are held for 48 hours before we release the stock.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
