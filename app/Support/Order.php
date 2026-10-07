<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A throwaway stand-in for an Eloquent Order. The whole point of this demo is
 * the mail layer, so the "database" is a couple of hard-coded arrays.
 */
final class Order
{
    /**
     * @param  array<int, array{name: string, qty: int, price: float}>  $items
     */
    public function __construct(
        public string $number,
        public string $customerName,
        public string $customerEmail,
        public array $items,
        public string $shippingAddress,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
    ) {}

    public static function sample(): self
    {
        return new self(
            number: 'A-10492',
            customerName: 'Ada Lovelace',
            customerEmail: 'ada@example.com',
            items: [
                ['name' => 'Mechanical Keyboard (Tactile)', 'qty' => 1, 'price' => 129.00],
                ['name' => 'USB-C Braided Cable, 2m', 'qty' => 2, 'price' => 14.50],
                ['name' => 'Desk Mat, Charcoal', 'qty' => 1, 'price' => 32.00],
            ],
            shippingAddress: "12 Analytical Way\nLondon, NW1 4RT\nUnited Kingdom",
            trackingNumber: 'TRK-99F204C1',
            carrier: 'Royal Mail',
        );
    }

    public function subtotal(): float
    {
        return array_reduce(
            $this->items,
            static fn (float $carry, array $item): float => $carry + ($item['qty'] * $item['price']),
            0.0,
        );
    }

    public function shipping(): float
    {
        return 4.99;
    }

    public function total(): float
    {
        return $this->subtotal() + $this->shipping();
    }

    public function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    public function receiptFilename(): string
    {
        return "receipt-{$this->number}.pdf";
    }
}
