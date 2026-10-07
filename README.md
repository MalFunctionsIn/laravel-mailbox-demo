# Mailbox for Laravel — a hands-on test project

A small Laravel 13 app built to put [`redberry/mailbox-for-laravel`](https://github.com/RedberryProducts/mailbox-for-laravel)
through its paces: a browser control panel that fires real outgoing mail, the
package's dashboard rendering it, and a test suite asserting against the
**fully rendered** messages.

Companion material for the video and the write-up lives in [`docs/`](docs/).

---

## What the package actually does

Set `MAIL_MAILER=mailbox` and Laravel's mail transport is swapped for one that
writes every outgoing message to local storage instead of handing it to an SMTP
server. Two things fall out of that:

1. **A dashboard at `/mailbox`** — read the mail you just "sent", with the HTML
   view, the plain-text alternative, headers and attachment previews.
2. **Assertions against the rendered message** — `assertSeeInHtml('$194.99')`
   runs against Blade output that actually compiled, not against a Mailable
   object that was never rendered.

Point 2 is the part `Mail::fake()` cannot do. `Mail::assertSent(OrderConfirmation::class)`
tells you the right class was queued. It will happily pass while the template
throws, the total is wrong, or the PDF never attached.

---

## Requirements

- PHP 8.3+
- Composer

No database server, no Docker, no Mailtrap account, no `npm install`.

---

## Running it

```bash
composer install
cp .env.example .env        # already provided and configured in this repo
php artisan key:generate
php artisan serve
```

The dashboard's compiled assets are committed under `public/vendor/mailbox/`, so
a fresh clone works immediately. Run `php artisan mailbox:install` to regenerate
them after a package upgrade.

Then open two windows side by side:

| URL | What it is |
| --- | --- |
| http://localhost:8000 | The control panel — buttons that send mail |
| http://localhost:8000/mailbox | The package's dashboard — the mail you sent |

Click a button on the left; the message shows up on the right on its own (the
dashboard polls every 2s, set via `MAILBOX_POLLING_INTERVAL`).

Prefer the terminal?

```bash
php artisan demo:mail            # interactive picker
php artisan demo:mail order      # order|shipment|cart|raw|all
php artisan mailbox:clear        # empty the mailbox
```

---

## The four things it sends

Each one exercises a different path into the mail transport, because the whole
point is that the transport sits underneath all of them.

| Send | Class | Covers |
| --- | --- | --- |
| Order confirmation | `App\Mail\OrderConfirmation` | HTML **and** text view, CC, BCC, Reply-To, two custom headers, generated PDF attachment |
| Shipment shipped | `App\Notifications\ShipmentShipped` | Laravel's notification system + Markdown mail theme |
| Abandoned cart | `App\Mail\AbandonedCart` | Markdown mailable, no attachments |
| Raw message | `Mail::raw()` | No view, no Mailable class at all |

The PDF receipt is generated at request time by `App\Support\ReceiptPdf`, which
hand-rolls a valid single-page PDF so the project stays dependency-free. In a
real app you'd use dompdf. It downloads and opens from the dashboard.

---

## The tests are the interesting part

```bash
php artisan test
```

```
Tests:    30 passed (152 assertions)
```

`phpunit.xml` routes test mail through the same transport:

```xml
<env name="MAIL_MAILER" value="mailbox"/>
<env name="MAILBOX_STORE_DRIVER" value="file"/>
<env name="MAILBOX_STORE_FILE_PATH" value="storage/framework/testing/mailbox"/>
<env name="MAILBOX_ATTACHMENTS_PATH" value="testing/attachments"/>
```

Add the trait and the mailbox is cleared before every test automatically:

```php
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;

class OrderConfirmationTest extends TestCase
{
    use InteractsWithMailbox;
    // ...
}
```

Pest users do the same thing with `uses(InteractsWithMailbox::class);`.

### What the assertions look like

```php
Mail::send(new OrderConfirmation(Order::sample()));

Mailbox::firstSent()
    ->assertFrom('shop@acme-store.test', 'ACME Store')
    ->assertHasTo('ada@example.com', 'Ada Lovelace')
    ->assertHasCc('warehouse@acme-store.test')
    ->assertHasBcc('audit@acme-store.test')
    ->assertHasReplyTo('support@acme-store.test', 'ACME Support')
    ->assertHasSubject('Order Confirmation — #A-10492')
    ->assertSeeInHtml('Thanks, Ada Lovelace!')
    ->assertSeeInHtml('$194.99')          // 129.00 + (2 × 14.50) + 32.00 + 4.99
    ->assertSeeInOrderInHtml(['Mechanical Keyboard', 'USB-C Braided Cable', 'Desk Mat'])
    ->assertSeeInText('Total:    $194.99')
    ->assertAttachmentCount(1)
    ->assertHasAttachment('receipt-A-10492.pdf', 'application/pdf')
    ->assertHasHeader('X-Order-Number', 'A-10492');
```

| File | What it demonstrates |
| --- | --- |
| `tests/Feature/OrderConfirmationTest.php` | Every per-message assertion against the kitchen-sink mailable |
| `tests/Feature/ShipmentNotificationTest.php` | Notifications; asserting the tracking button's real URL |
| `tests/Feature/MailboxCollectionTest.php` | Collection-level: `assertSent` with closures, `sent()`, isolation between tests |
| `tests/Feature/DemoPanelTest.php` | Driving the panel over HTTP, including a data provider across all four sends |
| `tests/Feature/FakeComparisonTest.php` | The control group — `Mail::fake()` and `Notification::fake()` staying green while the rendered email is wrong |

### The assertion that earns its keep

```php
// ShipmentNotificationTest
$this->mailbox()->firstSent()
    ->assertSeeInHtml('https://acme-store.test/track/TRK-99F204C1');
```

A correct tracking number pasted into a broken URL still produces a perfectly
valid `ShipmentShipped` object. `Notification::fake()` sees nothing wrong. This
assertion reads the href the customer will actually click.

That claim is checked in, not just asserted in prose. Truncate the URL in
`ShipmentShipped` to `'https://acme-store.test/track/'` and run the suite:

```
ShipmentNotificationTest  ✗ the tracking button points at the real tracking url
    Expected to see [https://acme-store.test/track/TRK-99F204C1] in the HTML body,
    but it was not found.

FakeComparisonTest        ✓ notification fake passes regardless of the rendered url
FakeComparisonTest        ✓ mail fake passes regardless of the rendered total
```

Same bug. One suite catches it; the fakes report success.

---

## Storage driver note

The package's default store is SQLite at `storage/app/mailbox/mailbox.sqlite`.
**This project is configured for the `file` driver instead**, because the machine
it was built on has no `pdo_sqlite` extension:

```ini
MAILBOX_STORE_DRIVER=file
```

Everything in the demo and the test suite behaves identically either way. To use
the package default, install the extension and drop that line:

```bash
sudo apt install php8.3-sqlite3 && sudo systemctl reload apache2   # or restart php-fpm
php artisan mailbox:install        # creates + migrates the sqlite store
```

`php artisan mailbox:install` will report a "could not find driver" error on the
migration step without `pdo_sqlite` — harmless here, since the `file` driver
needs no migrations. The config and dashboard assets still publish correctly.

---

## Security: the gate

Captured mail contains password-reset links and signed URLs, so the dashboard is
not something to leave open. Two defences ship on by default:

- `MAILBOX_ENABLED` is `false` whenever `APP_ENV=production` — the transport
  doesn't capture and the routes aren't even registered.
- Everywhere else the `viewMailbox` gate **denies every request** unless you
  define it yourself. The package's own default only allows `APP_ENV=local`.

For a staging box, define the ability explicitly:

```php
// app/Providers/AppServiceProvider.php
Gate::define('viewMailbox', fn ($user = null) => $user?->isStaff() ?? false);
```

---

## Capture and still deliver

To watch mail in the dashboard *and* send it for real — handy when you want to
check how Gmail renders something:

```ini
MAIL_MAILER=mailbox
MAILBOX_DECORATE=smtp
```

---

## Layout

```
app/
  Console/Commands/SendDemoMail.php   artisan demo:mail
  Mail/OrderConfirmation.php          HTML + text, cc/bcc, headers, PDF
  Mail/AbandonedCart.php              markdown, no attachments
  Notifications/ShipmentShipped.php   markdown notification
  Support/Order.php                   fake order, no database
  Support/ReceiptPdf.php              dependency-free PDF generator
resources/views/
  demo.blade.php                      the control panel
  emails/                             the three templates
tests/Feature/                        30 tests, 152 assertions
docs/
  youtube-script.md                   shot-by-shot recording script
  linkedin-article.md                 the write-up, ready to post
```
