<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Support\Order;
use Illuminate\Support\Facades\Mail;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;
use Tests\TestCase;

/**
 * The difference that matters: Mail::fake() can tell you a OrderConfirmation
 * object was handed to the mailer. These assertions run against the *rendered*
 * message — the Blade actually compiled, the PDF actually got attached, the
 * total actually came out as $194.99.
 */
class OrderConfirmationTest extends TestCase
{
    use InteractsWithMailbox;

    public function test_it_captures_a_single_message(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::assertSentCount(1);
        Mailbox::assertSentTo('ada@example.com');
        Mailbox::assertNotSentTo('someone-else@example.com');
    }

    public function test_it_addresses_the_envelope_correctly(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()
            ->assertFrom('shop@acme-store.test', 'ACME Store')
            ->assertHasTo('ada@example.com', 'Ada Lovelace')
            ->assertHasCc('warehouse@acme-store.test')
            ->assertHasBcc('audit@acme-store.test')
            ->assertHasReplyTo('support@acme-store.test', 'ACME Support')
            ->assertHasSubject('Order Confirmation — #A-10492')
            ->assertSubjectContains('A-10492');
    }

    public function test_the_rendered_html_shows_every_line_item_and_the_right_total(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()
            ->assertSeeInHtml('Thanks, Ada Lovelace!')
            ->assertSeeInHtml('Mechanical Keyboard (Tactile)')
            ->assertSeeInHtml('$129.00')
            // 129.00 + (2 x 14.50) + 32.00 + 4.99 shipping
            ->assertSeeInHtml('$194.99')
            ->assertDontSeeInHtml('{{')
            ->assertDontSeeInHtml('Lorem ipsum');
    }

    public function test_line_items_render_in_cart_order(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()->assertSeeInOrderInHtml([
            'Mechanical Keyboard (Tactile)',
            'USB-C Braided Cable, 2m',
            'Desk Mat, Charcoal',
            'Total',
        ]);
    }

    public function test_the_plain_text_alternative_is_rendered_too(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()
            ->assertSeeInText('ACME STORE')
            ->assertSeeInText('Order #A-10492')
            ->assertSeeInText('Total:    $194.99')
            ->assertSeeInOrderInText(['Subtotal:', 'Shipping:', 'Total:']);
    }

    public function test_the_pdf_receipt_is_attached(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()
            ->assertAttachmentCount(1)
            ->assertHasAttachment('receipt-A-10492.pdf', 'application/pdf');
    }

    public function test_tracking_headers_survive_rendering(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()
            ->assertHasHeader('X-Order-Number', 'A-10492')
            ->assertHasHeader('X-Mailbox-Demo', 'order-confirmation');
    }

    public function test_a_different_order_produces_a_different_total(): void
    {
        $order = new Order(
            number: 'B-77',
            customerName: 'Grace Hopper',
            customerEmail: 'grace@example.com',
            items: [['name' => 'Compiler Sticker', 'qty' => 3, 'price' => 2.00]],
            shippingAddress: "1 Navy Yard\nArlington, VA",
        );

        Mail::send(new OrderConfirmation($order));

        Mailbox::firstSent()
            ->assertHasTo('grace@example.com', 'Grace Hopper')
            ->assertHasSubject('Order Confirmation — #B-77')
            // 3 x 2.00 + 4.99
            ->assertSeeInHtml('$10.99')
            ->assertHasAttachment('receipt-B-77.pdf', 'application/pdf');
    }

    public function test_nothing_is_captured_when_nothing_is_sent(): void
    {
        Mailbox::assertNothingSent();
    }
}
