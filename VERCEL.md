# Deploying to Vercel

Vercel's Hobby plan is free with no sleeping, which is why this exists. The
catch is that a Vercel function has a **read-only filesystem apart from `/tmp`,
and `/tmp` is not shared between invocations** — so the mailbox cannot live on
disk. A message captured by the request that sent it would be gone before the
dashboard's request read it.

The fix is `MAILBOX_STORE_DRIVER=database`. That means one free external
Postgres, and it means attachments need a decision (see below).

If you'd rather not run a database, [`DEPLOY.md`](DEPLOY.md) covers Render's
free plan, where the app works exactly as built — file store, PDF attachments,
no external services. The trade is that Render sleeps after ~15 minutes idle.

---

## What you need

1. A Vercel account (Hobby, free).
2. A free Postgres database. [Neon](https://neon.tech) is the easiest — it
   hands you a connection string and has a free tier. Supabase and Vercel's own
   Postgres integration both work too. `pdo_pgsql` and `pdo_mysql` are both
   bundled in the runtime, so MySQL hosts like PlanetScale are fine as well.

## Steps

1. **Import the repo** at vercel.com → Add New → Project. Vercel reads
   `vercel.json`, so leave the framework preset as *Other* and don't set a
   build command.

2. **Set Application Preset to `Other`.** Vercel's import screen detects
   Laravel's `package.json` and offers `Vite`, which builds a frontend that
   does not exist here and then fails looking for `dist/`. A preset chosen on
   that screen is saved on the project and overrides `vercel.json`.

3. **Add every environment variable** from
   [`.env.vercel.example`](.env.vercel.example) — paste the file into Vercel's
   *Import .env* box and replace the two placeholders.

   Set all of them, not just the secrets. `vercel.json` carries an `env` block,
   but `env` is absent from Vercel's current `vercel.json` property list and is
   documented elsewhere as legacy, so it cannot be relied on to reach the
   running app. This repository has no committed `.env` either, which makes the
   dashboard the only source of configuration.

   The failure mode if they do not arrive is quiet rather than loud: `APP_ENV`
   falls back to `production`, which switches the mailbox package off entirely,
   and `MAIL_MAILER` falls back to `log`. The site loads, the buttons work, and
   nothing ever appears in the dashboard.

4. **Deploy.** The build runs `composer run vercel`, which checks the install
   and migrates the mailbox tables into your database.

Migrations run on every deploy and are idempotent, so there's no separate
release step.

## Attachments are off by default

`vercel.json` sets `MAILBOX_ATTACHMENTS_ENABLED=false`, and that's deliberate.

With the database store, attachment *metadata* goes into Postgres but the
*bytes* still go to a Laravel filesystem disk — which on Vercel is read-only.
The package's `mailbox` disk is configured with `throw => false`, so the write
fails quietly: you'd get an attachment row in the database, a paperclip in the
dashboard, and an empty file on download. A visible broken link is worse than
no link, hence off.

To get the PDF receipt back, give it real object storage. Cloudflare R2 has a
free tier and speaks S3:

```ini
MAILBOX_ATTACHMENTS_ENABLED=true
MAILBOX_ATTACHMENTS_DISK=s3

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=mailbox-demo
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

That needs `composer require league/flysystem-aws-s3-v3` as a real (non-dev)
dependency.

## Why the build can fail on purpose

`redberry/mailbox-for-laravel` sits in `require-dev` — correctly, since it
should never ship to production. But it is also the entire subject of this
demo, so if the build installs with `--no-dev` the package vanishes and the app
deploys "successfully", then 500s at runtime on an unknown `mailbox` mailer.

`scripts/vercel-build.php` checks for it and fails the build with an
explanation instead. If you hit it, either set `COMPOSER_FLAGS` to an empty
string in the Vercel project's build environment, or move the package from
`require-dev` to `require`.

## Testing the Vercel configuration without deploying

A deploy is a slow way to find a configuration mistake. `scripts/vercel-preflight.sh`
runs the app locally with exactly the settings a Vercel deployment uses — the
database-backed mailbox store, cookie sessions, attachments disabled, Blade
compiling into `/tmp` — and runs the same build checks first:

```bash
scripts/vercel-preflight.sh 'postgresql://user:pass@ep-x.neon.tech/neondb?sslmode=require'
```

Then open http://127.0.0.1:9200 and http://127.0.0.1:9200/mailbox and click
through. If messages appear there, the configuration is sound and any
remaining failure is on Vercel's side — the preset, the environment variables,
or the build.

It does not emulate the serverless runtime. It covers the part that actually
differs from local development, which is where the mistakes are.

To point it at a throwaway database instead of your real one:

```bash
docker run -d --name mailbox-pg -e MYSQL_ROOT_PASSWORD=preflight \
  -e MYSQL_DATABASE=mailbox_vercel -p 9307:3306 mysql:8
scripts/vercel-preflight.sh 'mysql://root:preflight@127.0.0.1:9307/mailbox_vercel'
```

## Deploying from the CLI instead of the dashboard

The dashboard hides build output behind a summary. The CLI prints the whole
thing and tends to say what actually went wrong:

```bash
npm i -g vercel
vercel login
vercel --prod
```

Run it from the project root. It reads the same `vercel.json`, and prompts for
project settings on first run — answer **Other** when it asks about the
framework.

## Troubleshooting

### `No Output Directory named "dist" found after the Build completed`

Vercel's framework auto-detection saw `package.json` and `vite.config.js` —
which Laravel ships by default — decided this was a Vite project, ran a
frontend build, and then looked for a `dist/` directory.

There is no frontend build in this app. The demo page uses inline CSS and the
mailbox dashboard ships its own compiled assets under
`public/vendor/mailbox/`. The only `@vite` call in the project is in Laravel's
stock `welcome.blade.php`, which no route serves.

`vercel.json` now sets `"framework": null` to stop the guessing, and
`.vercelignore` keeps the Vite scaffolding out of the upload so there is
nothing left to detect.

If the error survives a redeploy, the preset is still saved on the project from
the import screen. In **Project Settings → Build and Deployment**:

- **Framework Preset:** Other
- **Build Command:** leave empty (switch off any override)
- **Output Directory:** leave empty (switch off any override)

Then **Deployments → ⋯ → Redeploy**, with the build cache disabled.

### `BUILD FAILED — redberry/mailbox-for-laravel is not installed`

That is this project's own build guard, firing because the install dropped dev
dependencies. See *Why the build can fail on purpose* above.

### `419 Page Expired` on the send buttons

`APP_KEY` is not set, so the session cookie cannot be decrypted. Sessions are
already on the cookie driver, so this is the only cause.

### The dashboard returns 403

`DEMO_PUBLIC_MAILBOX` did not reach the runtime. Add it as `true` in the
project's environment variables.

## What each variable is for

| Variable | Value | Why |
| --- | --- | --- |
| `APP_ENV` | `demo` | Must not be `production` — the package goes inert there |
| `SESSION_DRIVER` | `cookie` | A file session writes the CSRF token in one invocation and validates it in another, so every button returns 419 |
| `CACHE_STORE` | `array` | The file cache driver needs a writable disk |
| `LOG_CHANNEL` | `stderr` | `storage/logs` is read-only; stderr reaches Vercel's log drain |
| `MAILBOX_STORE_DRIVER` | `database` | The whole reason this file exists |
| `DEMO_PUBLIC_MAILBOX` | `true` | Opens `/mailbox` to visitors. Safe here, never on real mail |
| `MAILBOX_ATTACHMENTS_ENABLED` | `false` | See above |

`api/index.php` additionally points `VIEW_COMPILED_PATH` at `/tmp` before
Laravel boots, so Blade can compile on a cold start.

The same values are mirrored in `vercel.json`'s `env` block. Treat that as a
fallback only — set them in the dashboard regardless.

## PHP version

`vercel.json` pins `vercel-php@0.7.4`, which is PHP 8.3 — the version this
project is tested against. `vercel-php@0.9.0` gives you PHP 8.5 if you want it;
the package requires `^8.3` so it should be fine, but it's untested here.

## What was verified, and what wasn't

Verified locally against a real MySQL 8.4 instance in Docker:

- `MAILBOX_STORE_DRIVER=database` captures and reads back all four message
  types, with attachment metadata landing in the database
- the package's migrations run clean on the `mailbox` connection
- the full 34-test suite passes against the database store, not just the
  file store
- `scripts/vercel-build.php` passes, fails on a missing package, and fails on
  a missing database, with the right exit codes

Not verified, because it needs your accounts: the actual Vercel deployment,
the `vercel-php` runtime's behaviour, and whether its `composer install`
includes dev dependencies. The build guard exists precisely because that last
one is unknown.

## Expect a cold start

The runtime reports ~250ms cold and ~5ms warm, but that's for a bare PHP file.
Laravel on a cold invocation also boots the framework and compiles Blade into
`/tmp`, so the first hit after an idle period will be noticeably slower — still
far better than Render's 30–60s wake-up.
