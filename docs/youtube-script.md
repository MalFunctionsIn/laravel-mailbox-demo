# YouTube script — "Mailbox for Laravel"

**Runtime target:** 9–11 minutes
**Format:** screen recording + voice-over, face cam optional in the intro/outro
**Repo on screen:** this project, already installed, tests green

---

## Titles (pick one)

1. **Stop Using Mailtrap — Test Laravel Emails Inside Your App**
2. **This Laravel Package Catches Your Email *And* Tests It**
3. **Mail::fake() Is Lying To You — Here's The Fix**

Thumbnail text: **MAIL::FAKE() LIES** with the dashboard screenshot behind it.

---

## Pre-flight checklist

- [ ] Two windows arranged side by side: control panel left, `/mailbox` right
- [ ] Terminal in a third space, font bumped to ~18pt, prompt shortened
- [ ] `php artisan mailbox:clear` run so the dashboard starts empty
- [ ] Browser zoom at 110%, bookmarks bar hidden, no personal tabs
- [ ] A second clean Laravel app ready for the live-install segment
- [ ] `docs/` and `.env` open in the editor, no secrets anywhere on screen
- [ ] Record at 1440p or higher so the code stays readable after compression

---

## 0:00 — Cold open (no intro music, start on the payoff)

**Screen:** split view. Click **Send order confirmation**. The message lands in
the dashboard on the right by itself.

> "I just sent an email from a Laravel app. It never left my laptop — no SMTP
> server, no Mailtrap account, no Docker container. And here it is: the HTML,
> the plain-text version, the headers, and the PDF receipt I can actually open."

**Screen:** click the attachment, the PDF opens.

> "That's nice. But it's not the reason I'm making this video."

---

## 0:30 — The hook: the problem with Mail::fake()

**Screen:** editor, type this out live.

```php
Mail::fake();

$this->post('/checkout');

Mail::assertSent(OrderConfirmation::class);
```

> "This is how most of us test email in Laravel. And it passes. It passes when
> the template throws an exception. It passes when the total is wrong. It passes
> when the PDF never attached. It passes when the unsubscribe link points at
> localhost."
>
> "Because `Mail::fake()` intercepts the mailable *before* it renders. You're
> asserting that you handed the right object to the mailer. You're not asserting
> anything about the email."

**Screen:** cut to a passing test, then show the broken template it passed on.

> "Mailbox for Laravel fixes both halves of this: you get the dashboard, and you
> get assertions that run against the message after Blade has compiled."

---

## 1:30 — Install it, live, in under a minute

**Screen:** the second clean Laravel app, terminal full screen.

```bash
composer require redberry/mailbox-for-laravel --dev
php artisan mailbox:install
```

> "Dev dependency, because this never belongs in production. The install command
> publishes the config and the dashboard assets."

Then `.env`:

```ini
MAIL_MAILER=mailbox
```

> "One line. That's the whole setup. `mailbox` is now a mail transport like
> `smtp` or `ses`, except it writes to local storage instead of a socket."

**Screen:** `php artisan serve`, open `/mailbox`, dashboard is empty.

**⚠️ Mention honestly:** if `pdo_sqlite` isn't installed, the migration step of
`mailbox:install` errors with "could not find driver". Show the fix on screen:

```ini
MAILBOX_STORE_DRIVER=file
```

> "Default store is a dedicated SQLite file. If you don't have the SQLite driver,
> switch to the file driver — JSON on disk, no database at all. That's what this
> demo repo uses, and nothing else changes."

---

## 3:00 — The tour: four ways mail gets out, one transport catches all of them

**Screen:** back to the split view, control panel on the left.

Click each and narrate while the dashboard fills:

1. **Order confirmation** — "HTML view *and* a plain-text alternative. Toggle
   between them up here. CC to the warehouse, BCC to an audit address, Reply-To
   pointed at support, two custom `X-` headers, and a generated PDF."
   *Show the headers panel and the text/HTML toggle.*

2. **Shipment shipped** — "This one isn't a Mailable at all, it's a
   Notification. The transport sits below both, so it's captured the same way."

3. **Abandoned cart** — "Markdown mailable, rendered through Laravel's own mail
   theme. No attachments."

4. **Raw message** — "`Mail::raw()`. No view, no class. The kind of thing a
   scheduled command fires at 3am. Still caught."

Then **Send the burst**:

> "Four at once — and notice I'm not refreshing. The dashboard polls, so it just
> updates."

**Screen:** demonstrate search, read/unread, and delete a message.

---

## 5:00 — The actual payoff: the test suite

**Screen:** `tests/Feature/OrderConfirmationTest.php`.

> "Here's where this stops being a Mailtrap replacement and starts being
> something Mailtrap can't do at all."

Walk through the setup first:

```xml
<!-- phpunit.xml -->
<env name="MAIL_MAILER" value="mailbox"/>
<env name="MAILBOX_STORE_DRIVER" value="file"/>
<env name="MAILBOX_STORE_FILE_PATH" value="storage/framework/testing/mailbox"/>
```

```php
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;

class OrderConfirmationTest extends TestCase
{
    use InteractsWithMailbox;   // clears the mailbox before every test
}
```

> "The trait handles isolation — mailbox is emptied before each test, so nothing
> leaks between them. Pest users: `uses(InteractsWithMailbox::class)`."

Then the assertion chain, read it out loud:

```php
Mail::send(new OrderConfirmation(Order::sample()));

Mailbox::firstSent()
    ->assertHasTo('ada@example.com', 'Ada Lovelace')
    ->assertHasCc('warehouse@acme-store.test')
    ->assertHasBcc('audit@acme-store.test')
    ->assertHasSubject('Order Confirmation — #A-10492')
    ->assertSeeInHtml('$194.99')
    ->assertSeeInOrderInHtml(['Mechanical Keyboard', 'USB-C Cable', 'Desk Mat'])
    ->assertSeeInText('Total:    $194.99')
    ->assertAttachmentCount(1)
    ->assertHasAttachment('receipt-A-10492.pdf', 'application/pdf')
    ->assertHasHeader('X-Order-Number', 'A-10492');
```

> "`$194.99` — that's a hundred and twenty-nine, plus two cables at fourteen
> fifty, plus the mat, plus shipping. I'm asserting the arithmetic came out
> right **in the rendered HTML**. And `assertHasAttachment` with the mime type —
> that PDF genuinely got generated and genuinely got attached."

**Screen:** `php artisan test`

```
Tests:    30 passed (152 assertions)
```

---

## 7:00 — The money demo: break something, watch it get caught

**Screen:** open `app/Notifications/ShipmentShipped.php`. Break the URL:

```php
// before
->action('Track your parcel', 'https://acme-store.test/track/'.$this->order->trackingNumber)

// after — a plausible copy-paste mistake
->action('Track your parcel', 'https://acme-store.test/track/')
```

> "Realistic bug. The tracking number's right there on the model, someone just
> dropped it out of the URL. The notification object is still perfectly valid."

**Screen:** run the suite.

```bash
php artisan test --filter=ShipmentNotificationTest
```

> "Fails. On the href the customer would have clicked."

Then show it the other way:

**Screen:** `tests/Feature/FakeComparisonTest.php` — already in the repo.

```bash
php artisan test --filter=FakeComparisonTest
```

> "Same bug still in the code. These two tests use `Notification::fake()` and
> `Mail::fake()` — and they're green. Both of them. That is the entire argument
> for this package in one screen."

**Screen:** revert the change, suite green again.

---

## 8:30 — The part people will skip and shouldn't: security

**Screen:** `config/mailbox.php`, the `gate` block.

> "Your mailbox holds password-reset links and signed URLs. Two things protect
> it by default, and you should know both."
>
> "One: `MAILBOX_ENABLED` is automatically false when `APP_ENV=production`. The
> transport doesn't capture and the routes aren't registered."
>
> "Two: everywhere else — staging, review apps — the `viewMailbox` gate denies
> every single request until you define it yourself. The package's default only
> opens in `local`. So if you deploy this to staging and the dashboard 403s,
> that's not a bug, that's the package refusing to leak your users' mail."

```php
Gate::define('viewMailbox', fn ($user = null) => $user?->isStaff() ?? false);
```

---

## 9:15 — One more trick: capture *and* deliver

```ini
MAIL_MAILER=mailbox
MAILBOX_DECORATE=smtp
```

> "Capture it locally *and* forward it to a real mailer. That's the move when you
> need to see how Gmail or Outlook actually renders the thing — you get the
> dashboard copy and the real delivery from one send."

---

## 9:45 — Verdict and outro

> "Where this wins: assertions against rendered mail, which `Mail::fake()`
> structurally cannot do. Zero-dependency local setup — no account, no container.
> And an honest default that locks the dashboard outside of local."
>
> "Where it doesn't replace anything: it's not an inbound mail handler, and it's
> not a cross-client rendering service. If you need to know how Outlook 2016
> renders your table, you still want Litmus or Email on Acid. Pair them — this
> for correctness, those for rendering."
>
> "Repo's in the description, including the whole test suite. If you've been
> running `Mail::assertSent` and calling it covered — go check one of your
> templates against the rendered output. Tell me in the comments what you find."

**End card:** repo link, package link, subscribe.

---

## Description (paste into YouTube)

```
Mail::fake() can't tell you whether your email actually rendered. It intercepts
the Mailable before Blade ever runs — so your test passes while the template
throws, the total is wrong, or the PDF never attached.

Mailbox for Laravel fixes both halves: a local dashboard at /mailbox that catches
outgoing mail with no SMTP server or Mailtrap account, plus assertions that run
against the fully rendered message.

In this video I install it from scratch, send four different kinds of mail
(Mailable, Notification, Markdown, Mail::raw), write the assertion suite, then
deliberately break a tracking URL to show what Notification::fake() misses.

CHAPTERS
0:00  The payoff first
0:30  Why Mail::fake() isn't enough
1:30  Installing it (one line of .env)
3:00  Four kinds of mail, one transport
5:00  The test suite
7:00  Breaking a URL on purpose
8:30  Securing the dashboard
9:15  Capture AND deliver for real
9:45  Verdict

LINKS
Demo repo:    <your repo URL>
Package:      https://github.com/RedberryProducts/mailbox-for-laravel
Laravel News: https://laravel-news.com/mailbox-for-laravel

Built by Redberry, maintained by Nika Jorjoliani. PHP 8.3+, Laravel 11/12/13.

#laravel #php #testing #webdev
```

---

## Pinned comment

```
One thing worth repeating from 8:30: the /mailbox dashboard holds real
password-reset links. MAILBOX_ENABLED is false in production automatically, and
the viewMailbox gate denies everything outside APP_ENV=local until you define it
yourself. If staging 403s on you — that's the package doing its job.
```
