<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Proves Mailbox captures mail sent through the notification system, not just
 * Mailables — the rendered MailMessage goes through the same transport.
 */
class ShipmentShipped extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your order #{$this->order->number} has shipped")
            ->greeting("Good news, {$this->order->customerName}!")
            ->line("Order #{$this->order->number} is on its way with {$this->order->carrier}.")
            ->line("Tracking number: {$this->order->trackingNumber}")
            ->action('Track your parcel', 'https://acme-store.test/track/'.$this->order->trackingNumber)
            ->line('Thanks for shopping with ACME Store.');
    }
}
