# Deploying the demo

The app is a single container with no database, no object storage and no queue
worker. It needs one thing from the host: **a writable filesystem**. Any
platform that builds a `Dockerfile` and runs a long-lived process will do —
Railway, Render, Fly.io, Koyeb, or a plain VPS.

Both recipes below were validated by building the image and driving the full
click-through inside the running container: all five send routes, the dashboard,
and the PDF attachment download.

---

## Railway

Railway detects the `Dockerfile` on its own, so there's no config file to add.

1. **New Project → Deploy from GitHub repo**, pick `laravel-mailbox-demo`.
2. Under **Variables**, add:

   | Variable | Value |
   | --- | --- |
   | `APP_KEY` | output of `php artisan key:generate --show` |
   | `APP_ENV` | `demo` |
   | `APP_URL` | the public URL Railway assigns |
   | `DEMO_PUBLIC_MAILBOX` | `true` |

3. **Settings → Networking → Generate Domain.**

Railway sets `PORT` itself and the entrypoint binds Apache to it. Don't hardcode
a port.

## Render (recommended)

A `render.yaml` blueprint is committed, so there is nothing to configure:

1. **New → Blueprint**, point it at the repo.
2. Render prompts for **`APP_KEY`** and nothing else. Generate one with
   `php artisan key:generate --show`.
3. **Apply.** First build takes a few minutes; later ones are cached.

Everything else — the mailer, the store driver, the dashboard gate — comes from
the blueprint. No database, no object storage, no accounts beyond Render.

The free plan is 0.1 CPU / 512 MB; the container idles at about 50 MB. A
workspace gets 750 instance-hours a month, and no credit card is required.

Two behaviours worth knowing before you put the link in a video description:

- **It sleeps.** After 15 minutes without a request the service spins down, and
  the next visitor waits roughly a minute for it to wake.
- **The mailbox starts empty** after every sleep or deploy, because free
  services get no persistent disk. For this demo that is arguably right —
  visitors get a clean slate and click the buttons themselves — but it means
  the dashboard looks empty until they send something.

## Fly.io

```bash
fly launch --no-deploy        # accept the Dockerfile, skip the database prompt
fly secrets set APP_KEY="$(php artisan key:generate --show)" APP_ENV=demo DEMO_PUBLIC_MAILBOX=true
fly deploy
```

Set `internal_port = 8080` in `fly.toml` to match the image's default.

---

## The environment variables that matter

Everything else has a working default baked into the `Dockerfile`.

| Variable | Why you need it |
| --- | --- |
| `APP_KEY` | Laravel refuses to encrypt sessions without it. The entrypoint generates a throwaway key if it's missing, so the app still boots — but sessions reset on every restart. Set it. |
| `APP_ENV` | Must **not** be `production`. The package sets `MAILBOX_ENABLED` to false there and goes completely inert. `demo` is what the image defaults to. |
| `APP_URL` | Only affects generated absolute URLs. Worth setting so the dashboard's attachment links point at your domain. |
| `DEMO_PUBLIC_MAILBOX` | Opens `/mailbox` to anonymous visitors. Without it the gate returns 403 outside `local`. |

## What persists and what doesn't

Captured mail lives in `storage/app/mailbox` as JSON. That's inside the
container, so **every redeploy or restart starts with an empty mailbox**. For a
demo that's a feature — visitors get a clean slate and click the buttons
themselves.

If you'd rather it survive, mount a volume at
`/var/www/html/storage/app/mailbox` (Railway: Settings → Volumes). Nothing in
the app changes.

## Why not Vercel or Netlify

Serverless functions get a read-only filesystem apart from `/tmp`, and `/tmp`
isn't shared between invocations. This app writes a message on one request and
reads it back on the next, so the dashboard would always look empty — you'd
click Send and nothing would arrive. Plain Laravel breaks the same way there:
with a file session driver, the CSRF token is written by one invocation and
validated by another, so every button returns 419.

Making it work on Vercel means a hosted database for `MAILBOX_STORE_DRIVER=database`,
S3 for attachments, and `SESSION_DRIVER=cookie` — two external services for an
app that needs none of them anywhere else.

## Security

`DEMO_PUBLIC_MAILBOX=true` lets anyone read the captured mail. That's fine here
because the only thing this app ever sends is fake ACME Store orders.

**Never set it on an app that sends real mail.** Captured messages contain
password reset links, signed URLs and customer email addresses. On a real
staging box, define the gate against your own users instead:

```php
// app/Providers/AppServiceProvider.php
Gate::define('viewMailbox', fn ($user = null) => $user?->isStaff() ?? false);
```

The gate's three states are pinned down in `tests/Feature/MailboxGateTest.php`.

## Running the image locally

```bash
docker build -t mailbox-demo .
docker run --rm -p 8080:8080 \
  -e APP_KEY="$(php artisan key:generate --show)" \
  -e APP_URL=http://localhost:8080 \
  mailbox-demo
```

Then http://localhost:8080 and http://localhost:8080/mailbox.
