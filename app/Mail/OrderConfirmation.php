<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Order;
use App\Support\ReceiptPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The kitchen-sink mailable: HTML + plain text, CC, BCC, Reply-To, a custom
 * header and a PDF attachment. Every one of those has a matching Mailbox
 * assertion, which is exactly why the demo sends it.
 */
class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmation — #{$this->order->number}",
            to: [new Address($this->order->customerEmail, $this->order->customerName)],
            cc: [new Address('warehouse@acme-store.test', 'ACME Warehouse')],
            bcc: [new Address('audit@acme-store.test', 'Order Audit Log')],
            replyTo: [new Address('support@acme-store.test', 'ACME Support')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            text: 'emails.order-confirmation-text',
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Order-Number' => $this->order->number,
            'X-Mailbox-Demo' => 'order-confirmation',
        ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => ReceiptPdf::for($this->order),
                $this->order->receiptFilename(),
            )->withMime('application/pdf'),
        ];
    }
}
