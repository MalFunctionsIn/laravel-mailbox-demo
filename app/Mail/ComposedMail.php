<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * An email assembled from whatever the visitor typed into the control panel.
 *
 * The body is treated as plain text and escaped into the HTML template rather
 * than passed through as markup. The demo is deployed on a public URL, so
 * letting anyone store arbitrary HTML that then renders in another visitor's
 * dashboard would be a stored-XSS hole. Composing is the point; raw HTML is
 * not needed to make it.
 */
class ComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'both'|'html'|'text'  $format
     * @param  array{name: string, mime: string, data: string}|null  $attachment
     */
    public function __construct(
        public string $toAddress,
        public string $subjectLine,
        public string $body,
        public string $format = 'both',
        public ?string $ccAddress = null,
        public ?array $attachment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            to: [new Address($this->toAddress)],
            cc: $this->ccAddress ? [new Address($this->ccAddress)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->format === 'text' ? null : 'emails.composed',
            text: $this->format === 'html' ? null : 'emails.composed-text',
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Mailbox-Demo' => 'composed',
        ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachment === null) {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => $this->attachment['data'],
                $this->attachment['name'],
            )->withMime($this->attachment['mime']),
        ];
    }
}
