<?php

use App\Mail\AbandonedCart;
use App\Mail\ComposedMail;
use App\Mail\OrderConfirmation;
use App\Notifications\ShipmentShipped;
use App\Support\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Facades\Mailbox;

/*
|--------------------------------------------------------------------------
| Mailbox for Laravel — demo routes
|--------------------------------------------------------------------------
| Each route fires one kind of outgoing mail. MAIL_MAILER=mailbox means none
| of it leaves the machine: the transport captures it and the dashboard at
| /mailbox renders it.
*/

Route::get('/', function () {
    return view('demo', [
        'order' => Order::sample(),
        'captured' => count(Mailbox::all()),
        'dashboard' => '/'.ltrim((string) config('mailbox.path'), '/'),
    ]);
})->name('demo');

Route::post('/send/order-confirmation', function () {
    $order = Order::sample();

    Mail::send(new OrderConfirmation($order));

    return back()->with('status', "Order confirmation for #{$order->number} captured — HTML + text, CC, BCC, Reply-To, a custom header and a PDF receipt.");
})->name('send.order');

Route::post('/send/shipment', function () {
    $order = Order::sample();

    Notification::route('mail', [$order->customerEmail => $order->customerName])
        ->notify(new ShipmentShipped($order));

    return back()->with('status', 'Shipment notification captured — sent through the notification system, not a Mailable.');
})->name('send.shipment');

Route::post('/send/abandoned-cart', function () {
    $order = Order::sample();

    Mail::send(new AbandonedCart($order));

    return back()->with('status', 'Abandoned cart captured — a Markdown mailable with no attachments.');
})->name('send.cart');

Route::post('/send/raw', function () {
    Mail::raw(
        "Heads up: the nightly import finished with 3 warnings.\n\nSee the admin log for details.",
        fn ($message) => $message
            ->to('ops@acme-store.test')
            ->subject('[cron] Nightly import finished with warnings'),
    );

    return back()->with('status', 'Mail::raw() captured — plain text, no view, no Mailable class.');
})->name('send.raw');

Route::post('/send/compose', function (Request $request) {
    $validated = $request->validate([
        'to' => ['required', 'email:rfc', 'max:255'],
        'cc' => ['nullable', 'email:rfc', 'max:255'],
        'subject' => ['required', 'string', 'max:200'],
        'body' => ['required', 'string', 'max:5000'],
        'format' => ['required', 'in:both,html,text'],
        'attachment' => ['nullable', 'file', 'max:1024', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv'],
    ], [], [
        'to' => 'recipient',
        'cc' => 'CC address',
    ]);

    $file = $request->file('attachment');

    Mail::send(new ComposedMail(
        toAddress: $validated['to'],
        subjectLine: $validated['subject'],
        body: $validated['body'],
        format: $validated['format'],
        ccAddress: $validated['cc'] ?? null,
        // Read the upload into memory rather than storing it: the message is
        // captured immediately and the file is never needed again.
        attachment: $file ? [
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'data' => $file->get(),
        ] : null,
    ));

    return back()->with('status', sprintf(
        'Captured "%s" to %s%s. Nothing left this machine.',
        $validated['subject'],
        $validated['to'],
        $file ? ' with '.$file->getClientOriginalName().' attached' : '',
    ));
})->name('send.compose');

Route::post('/send/burst', function () {
    $order = Order::sample();

    Mail::send(new OrderConfirmation($order));
    Mail::send(new AbandonedCart($order));

    Notification::route('mail', [$order->customerEmail => $order->customerName])
        ->notify(new ShipmentShipped($order));

    Mail::raw('Weekly digest: 42 orders, 3 refunds.', fn ($message) => $message
        ->to('owner@acme-store.test')
        ->subject('Weekly digest'));

    return back()->with('status', 'Sent all four at once — watch the dashboard pick them up without a refresh.');
})->name('send.burst');

Route::post('/demo/flush', function () {
    Mailbox::clearAll();

    return back()->with('status', 'Mailbox emptied. Same thing as running: php artisan mailbox:clear');
})->name('demo.flush');
