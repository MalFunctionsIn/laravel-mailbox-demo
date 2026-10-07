<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AbandonedCart;
use App\Mail\OrderConfirmation;
use App\Support\Order;
use Illuminate\Support\Facades\Mail;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;
use Tests\TestCase;

/**
 * Collection-level assertions: searching across everything the mailbox caught
 * during a single test, rather than drilling into one message.
 */
class MailboxCollectionTest extends TestCase
{
    use InteractsWithMailbox;

    public function test_raw_mail_is_captured_without_a_view_or_a_mailable(): void
    {
        Mail::raw('The nightly import finished with 3 warnings.', fn ($message) => $message
            ->to('ops@acme-store.test')
            ->subject('[cron] Nightly import finished with warnings'));

        Mailbox::assertSentCount(1);

        Mailbox::firstSent()
            ->assertHasTo('ops@acme-store.test')
            ->assertSubjectContains('[cron]')
            ->assertSeeInText('3 warnings')
            ->assertHasNoAttachments();
    }

    public function test_it_filters_a_busy_mailbox_with_a_closure(): void
    {
        $order = Order::sample();

        Mail::send(new OrderConfirmation($order));
        Mail::send(new AbandonedCart($order));
        Mail::raw('Weekly digest: 42 orders, 3 refunds.', fn ($message) => $message
            ->to('owner@acme-store.test')
            ->subject('Weekly digest'));

        Mailbox::assertSentCount(3);

        // Exactly one of the three carries an attachment.
        Mailbox::assertSent(
            fn (MailboxMessageData $message): bool => count($message->attachments ?? []) > 0,
            1,
        );

        Mailbox::assertSent(
            fn (MailboxMessageData $message): bool => str_contains((string) $message->subject, 'Weekly digest'),
        );

        Mailbox::assertNotSent(
            fn (MailboxMessageData $message): bool => str_contains((string) $message->subject, 'Refund issued'),
        );
    }

    public function test_sent_returns_a_filterable_collection(): void
    {
        $order = Order::sample();

        Mail::send(new OrderConfirmation($order));
        Mail::send(new AbandonedCart($order));

        $toAda = Mailbox::sent(
            fn (MailboxMessageData $message): bool => collect($message->to ?? [])
                ->pluck('email')
                ->contains('ada@example.com'),
        );

        $this->assertCount(2, $toAda);
        $this->assertContains('You left something behind', $toAda->pluck('subject')->all());
    }

    public function test_first_sent_can_pick_a_specific_message_out_of_the_pile(): void
    {
        $order = Order::sample();

        Mail::send(new OrderConfirmation($order));
        Mail::send(new AbandonedCart($order));

        Mailbox::firstSent(
            fn (MailboxMessageData $message): bool => str_contains((string) $message->subject, 'left something behind'),
        )
            ->assertHasSubject('You left something behind')
            ->assertSeeInHtml('Finish checking out')
            ->assertSeeInHtml('Carts are held for 48 hours')
            ->assertHasNoAttachments();
    }

    public function test_the_mailbox_is_isolated_between_tests(): void
    {
        // Previous tests in this class sent plenty. The trait cleared it.
        Mailbox::assertNothingSent();

        Mail::raw('one', fn ($message) => $message->to('a@example.com')->subject('One'));

        Mailbox::assertSentCount(1);
    }

    public function test_clearing_the_mailbox_mid_test_works(): void
    {
        Mail::raw('one', fn ($message) => $message->to('a@example.com')->subject('One'));
        Mailbox::assertSentCount(1);

        $this->clearMailbox();

        Mailbox::assertNothingSent();
    }
}
