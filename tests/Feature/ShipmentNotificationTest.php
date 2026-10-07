<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Notifications\ShipmentShipped;
use App\Support\Order;
use Illuminate\Support\Facades\Notification;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;
use Tests\TestCase;

/**
 * Notification::fake() would stop the notification before it ever rendered.
 * Here it renders through the real Markdown theme, so the test can assert on
 * the button URL that the customer will actually click.
 */
class ShipmentNotificationTest extends TestCase
{
    use InteractsWithMailbox;

    private function notify(?Order $order = null): Order
    {
        $order ??= Order::sample();

        Notification::route('mail', [$order->customerEmail => $order->customerName])
            ->notify(new ShipmentShipped($order));

        return $order;
    }

    public function test_it_renders_the_markdown_notification(): void
    {
        $order = $this->notify();

        $this->mailbox()->assertSentCount(1);

        $this->mailbox()->firstSent()
            ->assertHasTo($order->customerEmail, $order->customerName)
            ->assertHasSubject('Your order #A-10492 has shipped')
            ->assertSeeInHtml('Good news, Ada Lovelace!')
            ->assertSeeInHtml('Royal Mail')
            ->assertSeeInHtml('TRK-99F204C1')
            ->assertHasNoAttachments();
    }

    public function test_the_tracking_button_points_at_the_real_tracking_url(): void
    {
        $this->notify();

        // The bug this catches: a correct tracking number pasted into a broken
        // URL. Mail::fake() sees a valid ShipmentShipped and says nothing.
        $this->mailbox()->firstSent()
            ->assertSeeInHtml('https://acme-store.test/track/TRK-99F204C1')
            ->assertSeeInText('https://acme-store.test/track/TRK-99F204C1');
    }

    public function test_it_reads_the_tracking_details_off_the_order(): void
    {
        $this->notify(new Order(
            number: 'C-5',
            customerName: 'Katherine Johnson',
            customerEmail: 'katherine@example.com',
            items: [['name' => 'Slide Rule', 'qty' => 1, 'price' => 40.00]],
            shippingAddress: 'Hampton, VA',
            trackingNumber: 'TRK-APOLLO11',
            carrier: 'FedEx',
        ));

        $this->mailbox()->firstSent()
            ->assertHasSubject('Your order #C-5 has shipped')
            ->assertSeeInHtml('Good news, Katherine Johnson!')
            ->assertSeeInHtml('FedEx')
            ->assertSeeInHtml('TRK-APOLLO11')
            ->assertDontSeeInHtml('Royal Mail');
    }
}
