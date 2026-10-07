<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Notifications\ShipmentShipped;
use App\Support\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The control group. These tests use Laravel's own fakes and pass even when the
 * rendered email is wrong — which is the entire argument for asserting against
 * the real rendered message instead.
 *
 * Keep them green: they are documentation, not coverage.
 */
class FakeComparisonTest extends TestCase
{
    public function test_notification_fake_passes_regardless_of_the_rendered_url(): void
    {
        Notification::fake();

        $order = Order::sample();

        Notification::route('mail', [$order->customerEmail => $order->customerName])
            ->notify(new ShipmentShipped($order));

        // Green whether the tracking URL is correct, truncated, or pointing at
        // localhost. The notification is never rendered, so there is nothing to
        // be wrong about.
        Notification::assertSentOnDemand(ShipmentShipped::class);
    }

    public function test_mail_fake_passes_regardless_of_the_rendered_total(): void
    {
        Mail::fake();

        Mail::send(new OrderConfirmation(Order::sample()));

        // Blade never compiled. The PDF closure never ran. A broken template
        // would not have thrown here.
        Mail::assertSent(OrderConfirmation::class);
    }
}
