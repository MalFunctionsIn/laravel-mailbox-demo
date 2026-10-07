<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Mailbox for Laravel</title>
    <style>
        :root {
            --bg: #09090b; --panel: #121214; --line: #27272a;
            --text: #fafafa; --muted: #a1a1aa; --accent: #f05340; --accent-soft: #3b1a16;
            --ok: #4ade80;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font: 15px/1.6 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 920px; margin: 0 auto; padding: 48px 20px 80px; }
        header { border-bottom: 1px solid var(--line); padding-bottom: 28px; margin-bottom: 32px; }
        .eyebrow {
            display: inline-block; font-size: 12px; letter-spacing: .08em; text-transform: uppercase;
            color: var(--accent); background: var(--accent-soft); padding: 4px 10px; border-radius: 99px;
            margin-bottom: 14px; font-weight: 600;
        }
        h1 { margin: 0 0 10px; font-size: 30px; line-height: 1.2; letter-spacing: -0.02em; }
        .lede { margin: 0; color: var(--muted); max-width: 62ch; }
        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .88em;
            background: #1c1c1f; border: 1px solid var(--line); border-radius: 5px; padding: 1px 5px;
        }
        .status {
            display: flex; gap: 10px; align-items: flex-start;
            background: #0f2417; border: 1px solid #1f4d30; color: #d7f5e3;
            border-radius: 10px; padding: 14px 16px; margin-bottom: 28px; font-size: 14px;
        }
        .status svg { flex: 0 0 auto; margin-top: 2px; }
        .bar {
            display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;
            background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
            padding: 16px 20px; margin-bottom: 28px;
        }
        .count { font-size: 14px; color: var(--muted); }
        .count strong { color: var(--text); font-size: 22px; font-variant-numeric: tabular-nums; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            font: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
            border-radius: 8px; padding: 9px 16px; border: 1px solid var(--line);
            background: #1c1c1f; color: var(--text); text-decoration: none; display: inline-block;
            transition: border-color .15s, background .15s;
        }
        .btn:hover { border-color: #52525b; background: #232327; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: #d8402f; border-color: #d8402f; }
        .btn-ghost { background: transparent; }
        .grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .card {
            background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
            padding: 20px; display: flex; flex-direction: column; gap: 12px;
        }
        .card h3 { margin: 0; font-size: 16px; letter-spacing: -0.01em; }
        .card p { margin: 0; font-size: 13.5px; color: var(--muted); flex: 1; }
        .tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .tag {
            font-size: 11px; font-family: ui-monospace, Menlo, monospace; color: #c4c4c8;
            background: #1a1a1d; border: 1px solid var(--line); border-radius: 5px; padding: 3px 7px;
        }
        h2.section {
            font-size: 13px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted);
            margin: 36px 0 16px; font-weight: 600;
        }
        footer { margin-top: 44px; padding-top: 24px; border-top: 1px solid var(--line); color: var(--muted); font-size: 13px; }
        footer a { color: var(--accent); }
        form { margin: 0; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <span class="eyebrow">redberry/mailbox-for-laravel</span>
        <h1>Catch outgoing mail without leaving Laravel</h1>
        <p class="lede">
            <code>MAIL_MAILER=mailbox</code> swaps Laravel's transport for one that writes every
            outgoing message to local storage instead of an SMTP server. Fire any of the sends
            below, then open the dashboard — no Mailtrap account, no Docker container, no
            <code>php artisan tinker</code> guesswork.
        </p>
    </header>

    @if (session('status'))
        <div class="status">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="7" stroke="#4ade80" stroke-width="1.5"/>
                <path d="M5 8.2l2 2L11 6" stroke="#4ade80" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="bar">
        <span class="count">
            <strong>{{ $captured }}</strong>
            message{{ $captured === 1 ? '' : 's' }} currently in the mailbox
        </span>
        <span class="actions">
            <a class="btn btn-primary" href="{{ $dashboard }}" target="_blank" rel="noopener">
                Open dashboard &nearr;
            </a>
            <form method="POST" action="{{ route('demo.flush') }}">
                @csrf
                <button class="btn btn-ghost" type="submit">Empty mailbox</button>
            </form>
        </span>
    </div>

    <h2 class="section">Send something</h2>

    <div class="grid">
        <div class="card">
            <h3>Order confirmation</h3>
            <p>
                The kitchen sink: an HTML view <em>and</em> a plain-text view, a CC, a BCC,
                a Reply-To, two custom headers, and a generated PDF receipt attached.
            </p>
            <div class="tags">
                <span class="tag">Mailable</span>
                <span class="tag">attachment</span>
                <span class="tag">cc / bcc</span>
                <span class="tag">headers</span>
            </div>
            <form method="POST" action="{{ route('send.order') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Send order confirmation</button>
            </form>
        </div>

        <div class="card">
            <h3>Shipment shipped</h3>
            <p>
                Goes out through Laravel's notification system rather than a Mailable, to show
                the transport sits below both. Rendered with the framework's Markdown theme.
            </p>
            <div class="tags">
                <span class="tag">Notification</span>
                <span class="tag">markdown</span>
                <span class="tag">on-demand route</span>
            </div>
            <form method="POST" action="{{ route('send.shipment') }}">
                @csrf
                <button class="btn" type="submit">Send notification</button>
            </form>
        </div>

        <div class="card">
            <h3>Abandoned cart</h3>
            <p>
                A Markdown mailable with no attachments — the control case for
                <code>assertHasNoAttachments()</code> and ordered-content assertions.
            </p>
            <div class="tags">
                <span class="tag">Mailable</span>
                <span class="tag">markdown</span>
                <span class="tag">no attachments</span>
            </div>
            <form method="POST" action="{{ route('send.cart') }}">
                @csrf
                <button class="btn" type="submit">Send cart reminder</button>
            </form>
        </div>

        <div class="card">
            <h3>Raw message</h3>
            <p>
                <code>Mail::raw()</code> with no view and no class at all — the kind of thing a
                scheduled command fires. Captured just the same.
            </p>
            <div class="tags">
                <span class="tag">Mail::raw()</span>
                <span class="tag">text only</span>
            </div>
            <form method="POST" action="{{ route('send.raw') }}">
                @csrf
                <button class="btn" type="submit">Send raw mail</button>
            </form>
        </div>
    </div>

    <h2 class="section">Or all at once</h2>

    <div class="card">
        <h3>Send all four</h3>
        <p>
            Leave the dashboard open in another window first. It polls every
            {{ (int) config('mailbox.polling.interval') }}ms, so the messages appear on their own.
        </p>
        <form method="POST" action="{{ route('send.burst') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Send the burst</button>
        </form>
    </div>

    <footer>
        Storage driver: <code>{{ config('mailbox.store.driver') }}</code> &nbsp;·&nbsp;
        Dashboard path: <code>{{ config('mailbox.path') }}</code> &nbsp;·&nbsp;
        Retention: <code>{{ (int) config('mailbox.retention') }}s</code><br>
        Assertions for all of this live in <code>tests/Feature/</code> — run them with
        <code>php artisan test</code>.
    </footer>
</div>
</body>
</html>
