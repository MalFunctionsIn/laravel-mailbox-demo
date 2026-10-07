<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;
use Tests\TestCase;

/**
 * Drives the demo through HTTP the same way a viewer will click it, so the
 * buttons in the browser and the assertions in CI cover the same code path.
 */
class DemoPanelTest extends TestCase
{
    use InteractsWithMailbox;

    public function test_the_control_panel_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Catch outgoing mail without leaving Laravel')
            ->assertSee('<strong>0</strong>', false)
            ->assertSee('Send order confirmation');
    }

    public function test_the_panel_reports_how_many_messages_are_captured(): void
    {
        $this->post('/send/order-confirmation')->assertRedirect('/');
        $this->post('/send/raw')->assertRedirect('/');

        $this->get('/')->assertSee('<strong>2</strong>', false);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sendRoutes(): array
    {
        return [
            'order confirmation' => ['/send/order-confirmation', 'Order Confirmation — #A-10492'],
            'shipment notification' => ['/send/shipment', 'Your order #A-10492 has shipped'],
            'abandoned cart' => ['/send/abandoned-cart', 'You left something behind'],
            'raw mail' => ['/send/raw', '[cron] Nightly import finished with warnings'],
        ];
    }

    #[DataProvider('sendRoutes')]
    public function test_each_button_captures_exactly_one_message(string $uri, string $subject): void
    {
        $this->post($uri)
            ->assertRedirect('/')
            ->assertSessionHas('status');

        Mailbox::assertSentCount(1);
        Mailbox::firstSent()->assertHasSubject($subject);
    }

    public function test_the_burst_button_captures_four_messages(): void
    {
        $this->post('/send/burst')->assertRedirect('/');

        Mailbox::assertSentCount(4);
        Mailbox::assertSentTo('ada@example.com');
        Mailbox::assertSentTo('owner@acme-store.test');
    }

    public function test_the_flush_button_empties_the_mailbox(): void
    {
        $this->post('/send/burst');
        Mailbox::assertSentCount(4);

        $this->post('/demo/flush')
            ->assertRedirect('/')
            ->assertSessionHas('status');

        Mailbox::assertNothingSent();
    }

    public function test_the_dashboard_is_reachable_in_the_local_environment(): void
    {
        // The package's default gate only opens in "local", so the dashboard
        // stays shut in testing/staging/production until you define your own.
        $this->app['env'] = 'local';

        $this->get('/mailbox')->assertOk();
    }
}
