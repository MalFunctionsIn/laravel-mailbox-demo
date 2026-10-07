<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A Markdown mailable with no attachments — the counterpart to
 * OrderConfirmation, so the demo can show assertHasNoAttachments()
 * and assertSeeInOrderInHtml() against a predictable item list.
 */
class AbandonedCart extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You left something behind',
            to: [$this->order->customerEmail],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.abandoned-cart');
    }
}
