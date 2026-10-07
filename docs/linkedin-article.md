# LinkedIn content

Two versions below. **Version A** is the feed post — that's what actually gets
reach, post this first. **Version B** is the long-form Article for the "Write
article" editor, worth publishing a day later and linking from the post.

---

# Version A — feed post

> Paste as-is. ~2,130 characters, inside LinkedIn's 3,000 limit. The feed folds
> after roughly 210, so the first three lines have to carry it. Keep the repo
> link out of the body and put it in the first comment instead.

```
Mail::fake() has been lying to me for years.

This test passes:

    Mail::fake();
    $this->post('/checkout');
    Mail::assertSent(OrderConfirmation::class);

It also passes when the Blade template throws. When the order total is wrong.
When the PDF invoice never attached. When the tracking URL is missing the
tracking number.

Because Mail::fake() intercepts the mailable BEFORE it renders. You're asserting
that you handed the right object to the mailer. You are not asserting anything
at all about the email your customer receives.

I spent the weekend testing a package that closes the gap — Mailbox for Laravel,
by Redberry. Two things, one dev dependency:

1. A local dashboard at /mailbox that catches outgoing mail. No SMTP server, no
Mailtrap account, no Docker container. One line: MAIL_MAILER=mailbox

2. Assertions that run against the fully rendered message:

    Mailbox::firstSent()
        ->assertHasCc('warehouse@acme-store.test')
        ->assertSeeInHtml('$194.99')
        ->assertSeeInText('Total:    $194.99')
        ->assertHasAttachment('receipt-A-10492.pdf', 'application/pdf')
        ->assertHasHeader('X-Order-Number', 'A-10492');

That $194.99 is arithmetic I'm checking in compiled HTML. That attachment
assertion means the PDF genuinely got generated and genuinely got attached.

So I built a demo app and tried to fool it. I truncated a tracking URL in a
notification — dropped the tracking number, left the slash. Realistic
copy-paste bug.

Mailbox caught it, on the exact href the customer would have clicked.
Notification::fake() stayed green.

Both tests are committed in the repo. Same bug, same run, opposite verdicts.

What it is not: an inbound mail handler, and not a cross-client rendering
service. If you need to know how Outlook 2016 handles your table, you still want
Litmus. Pair them — this for correctness, those for rendering.

Worth 20 minutes of your week: pick your most important transactional email and
assert against the rendered output. I'd genuinely like to know what you find.

Demo repo + full test suite in the comments.

#laravel #php #testing #softwareengineering
```

**First comment (post immediately after):**

```
Demo repo, including the 30-test suite and the two Mail::fake() control tests:
<your repo URL>

Package: github.com/RedberryProducts/mailbox-for-laravel
Built by Redberry, maintained by Nika Jorjoliani. PHP 8.3+, Laravel 11/12/13.

Walkthrough video: <your YouTube URL>
```

---

# Version B — long-form Article

**Headline:** Your Laravel email tests are probably passing for the wrong reason

**Subtitle:** `Mail::fake()` never renders the email. Here's what that costs you, and a package that fixes it.

---

There's a test in almost every Laravel codebase I've worked on that looks like
this:

```php
Mail::fake();

$this->post('/checkout', $payload);

Mail::assertSent(OrderConfirmation::class);
```

It's in the docs. It's fast. It's green. And it is asserting far less than
almost anyone reading it believes.

`Mail::fake()` swaps the mailer for a fake that records what it was handed. The
`OrderConfirmation` object gets recorded and thrown away. Blade never compiles.
The attachment closure never runs. The `$order->total()` call that builds the
line you actually care about is never evaluated.

So that test passes when the template throws a `ViewException`. It passes when
the total is off by the shipping cost. It passes when the invoice PDF silently
fails to generate. It passes when someone refactors a Blade partial out from
under it.

You have a test asserting you called the mailer correctly. You do not have a
test asserting you sent a correct email.

## What's normally used to cover the gap

Most teams fill it manually. Point `MAIL_MAILER` at Mailtrap or Mailpit, trigger
the flow by hand, and look at the result with human eyes. That works, and it's
genuinely useful for checking how something renders — but it isn't a test. It
doesn't run in CI, it doesn't run on the pull request that breaks it, and it
depends on somebody remembering to look.

## A package that does both

[Mailbox for Laravel](https://github.com/RedberryProducts/mailbox-for-laravel),
built by Redberry and maintained by Nika Jorjoliani, registers a mail transport
that writes outgoing messages to local storage instead of handing them to an
SMTP server.

```bash
composer require redberry/mailbox-for-laravel --dev
php artisan mailbox:install
```

```ini
MAIL_MAILER=mailbox
```

That's the setup. You get a dashboard at `/mailbox` with the HTML view, the
plain-text alternative, full headers and attachment previews — no account, no
container, nothing to keep running.

But the dashboard isn't the interesting half.

## Assertions against the rendered message

Because the transport sits at the bottom of the stack, the message it stores has
already been through Blade. Which means you can assert against it:

```php
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;

class OrderConfirmationTest extends TestCase
{
    use InteractsWithMailbox;   // clears the mailbox before each test

    public function test_the_confirmation_is_correct(): void
    {
        Mail::send(new OrderConfirmation(Order::sample()));

        Mailbox::firstSent()
            ->assertHasTo('ada@example.com', 'Ada Lovelace')
            ->assertHasCc('warehouse@acme-store.test')
            ->assertHasBcc('audit@acme-store.test')
            ->assertHasReplyTo('support@acme-store.test')
            ->assertHasSubject('Order Confirmation — #A-10492')
            ->assertSeeInHtml('$194.99')
            ->assertSeeInOrderInHtml(['Keyboard', 'USB-C Cable', 'Desk Mat'])
            ->assertSeeInText('Total:    $194.99')
            ->assertAttachmentCount(1)
            ->assertHasAttachment('receipt-A-10492.pdf', 'application/pdf')
            ->assertHasHeader('X-Order-Number', 'A-10492');
    }
}
```

Every one of those lines is checking something `Mail::fake()` structurally
cannot reach. `$194.99` is the sum of three line items plus shipping, asserted
in compiled HTML. The attachment assertion means a PDF was really generated and
really attached, with the right mime type. `assertSeeInText` covers the
plain-text alternative that nobody ever looks at and every spam filter does.

There's a collection-level API too — `assertSentCount`, `assertSentTo`,
`assertNothingSent`, and `assertSent` with a closure for filtering a busy
mailbox. The transport catches Mailables, mail Notifications and `Mail::raw()`
alike, since it sits below all three.

## I tried to fool it

I built a small demo app — a fake store that sends an order confirmation, a
shipment notification, an abandoned-cart reminder and a raw cron warning — and
then introduced a bug I've genuinely shipped before.

The shipment notification builds a tracking link. I truncated it: dropped the
tracking number, left the trailing slash. The `ShipmentShipped` object stays
completely valid. The notification still sends. Only the href is wrong.

Mailbox failed on it:

```
✗ the tracking button points at the real tracking url
  Expected to see [https://acme-store.test/track/TRK-99F204C1] in the HTML body,
  but it was not found.
```

With the same bug in place, `Notification::fake()` and `Mail::fake()` both
passed. I left those two tests committed in the repo as a control group, because
the comparison is the whole argument and it deserves to be reproducible rather
than just asserted in a blog post.

## Two things worth knowing before you deploy it

**It's a dev dependency, and the defaults are serious about that.**
`MAILBOX_ENABLED` is false whenever `APP_ENV=production` — the transport stops
capturing and the routes aren't registered at all. Everywhere else, the
`viewMailbox` gate denies every request until you define it, because the
package's own default only opens in `local`. Captured mail contains
password-reset links and signed URLs. If you put this on staging and the
dashboard 403s, that's the package working:

```php
Gate::define('viewMailbox', fn ($user = null) => $user?->isStaff() ?? false);
```

**The default store is SQLite, and it assumes you have the driver.** It creates
a dedicated database at `storage/app/mailbox/mailbox.sqlite`. On a box without
`pdo_sqlite`, the migration step of `mailbox:install` errors out. The fix is one
line — `MAILBOX_STORE_DRIVER=file` gives you JSON on disk and needs no database
at all. There's a `database` driver for an existing connection too.

One more flag worth knowing: `MAILBOX_DECORATE=smtp` captures locally *and*
forwards to a real mailer, for when you need both the dashboard copy and a
genuine delivery to check in Gmail.

## What it doesn't replace

It isn't an inbound mail handler — that's a different package. And it isn't a
cross-client rendering service. It tells you your email is *correct*; it can't
tell you how Outlook 2016 renders your nested table. Litmus and Email on Acid
still have a job. The two concerns pair well: this one in CI on every pull
request, those before a redesign ships.

## The part worth acting on

Pick the single most important transactional email in your application — the
receipt, the password reset, the invoice. Open the test that covers it. If it
stops at `Mail::assertSent(...)`, then today you have no automated knowledge of
whether that email renders at all.

That's a twenty-minute fix now. It's a support ticket and a refund later.

---

*Demo app with the full 30-test suite, including the `Mail::fake()` control
tests: `<your repo URL>`. Video walkthrough: `<your YouTube URL>`.*
