<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vercel entrypoint
|--------------------------------------------------------------------------
|
| Every request Vercel routes to this app arrives here (see vercel.json).
| A Vercel function gets a read-only filesystem apart from /tmp, so anything
| Laravel wants to write has to be pointed somewhere else before the
| framework boots.
|
| /tmp is per-invocation and not shared between them, which is exactly why
| the mailbox itself cannot live on disk here — MAILBOX_STORE_DRIVER=database
| sends captured mail to Postgres instead. /tmp is still fine for throwaway
| work like compiled Blade templates, which are rebuilt on a cold start.
|
*/

$tmp = '/tmp/storage';

$writable = [
    $tmp.'/framework/views',
    $tmp.'/framework/cache/data',
    $tmp.'/framework/sessions',
    $tmp.'/logs',
    $tmp.'/app',
];

foreach ($writable as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0o777, true);
    }
}

// config/view.php reads this; without it Laravel tries to compile into the
// bundle's storage/framework/views, which is read-only here.
$compiled = $tmp.'/framework/views';

putenv('VIEW_COMPILED_PATH='.$compiled);
$_ENV['VIEW_COMPILED_PATH'] = $compiled;
$_SERVER['VIEW_COMPILED_PATH'] = $compiled;

require __DIR__.'/../public/index.php';
