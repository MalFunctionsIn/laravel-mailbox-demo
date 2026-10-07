<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\AbandonedCart;
use App\Mail\OrderConfirmation;
use App\Notifications\ShipmentShipped;
use App\Support\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Redberry\MailboxForLaravel\Facades\Mailbox;

use function Laravel\Prompts\select;

class SendDemoMail extends Command
{
    protected $signature = 'demo:mail {kind? : order|shipment|cart|raw|all}';

    protected $description = 'Fire demo mail at the Mailbox transport without touching the browser.';

    public function handle(): int
    {
        $kind = $this->argument('kind') ?? select(
            label: 'What should I send?',
            options: [
                'order' => 'Order confirmation (HTML + text, cc/bcc, PDF attachment)',
                'shipment' => 'Shipment notification (Markdown, via Notification)',
                'cart' => 'Abandoned cart (Markdown mailable)',
                'raw' => 'Raw text mail (Mail::raw)',
                'all' => 'All of the above',
            ],
            default: 'order',
        );

        $order = Order::sample();

        $senders = [
            'order' => fn () => Mail::send(new OrderConfirmation($order)),
            'cart' => fn () => Mail::send(new AbandonedCart($order)),
            'shipment' => fn () => Notification::route('mail', [$order->customerEmail => $order->customerName])
                ->notify(new ShipmentShipped($order)),
            'raw' => fn () => Mail::raw(
                'The nightly import finished with 3 warnings.',
                fn ($message) => $message
                    ->to('ops@acme-store.test')
                    ->subject('[cron] Nightly import finished with warnings'),
            ),
        ];

        $kinds = $kind === 'all' ? array_keys($senders) : [$kind];

        foreach ($kinds as $one) {
            if (! isset($senders[$one])) {
                $this->components->error("Unknown kind [{$one}]. Try order, shipment, cart, raw or all.");

                return self::FAILURE;
            }

            $senders[$one]();
            $this->components->twoColumnDetail($one, '<fg=green>captured</>');
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d message(s) in the mailbox. Open %s to read them.',
            count(Mailbox::all()),
            rtrim((string) config('app.url'), '/').'/'.ltrim((string) config('mailbox.path'), '/'),
        ));

        return self::SUCCESS;
    }
}
