<?php

declare(strict_types=1);
use Redberry\MailboxForLaravel\MailboxServiceProvider;

/*
|--------------------------------------------------------------------------
| Vercel build step
|--------------------------------------------------------------------------
|
| Run from the "vercel" composer script, which Vercel's PHP runtime calls
| during the build.
|
| Its real job is the first check below. redberry/mailbox-for-laravel is a
| require-dev dependency — correctly, since nobody should ship it to
| production — but it is also the entire subject of this demo. If the build
| installed with --no-dev, the package is simply absent and the app would
| deploy "successfully" and then fail at runtime with an unhelpful error
| about an unknown "mailbox" mailer. Better to fail here, loudly.
|
*/

$autoload = __DIR__.'/../vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, PHP_EOL.'  BUILD FAILED  '.PHP_EOL.PHP_EOL
        .'vendor/autoload.php is missing — composer install did not run.'.PHP_EOL.PHP_EOL);
    exit(1);
}

require $autoload;

$fail = static function (string $message): never {
    fwrite(STDERR, PHP_EOL.'  BUILD FAILED  '.PHP_EOL.PHP_EOL.$message.PHP_EOL.PHP_EOL);
    exit(1);
};

$step = static function (string $message): void {
    fwrite(STDERR, '  → '.$message.PHP_EOL);
};

$step('Checking that the mailbox package survived composer install');

if (! class_exists(MailboxServiceProvider::class)) {
    $fail(
        "redberry/mailbox-for-laravel is not installed.\n\n".
        "The build ran composer install with --no-dev, which drops it: it sits in\n".
        "require-dev. The app cannot work without it — \"mailbox\" is the mail\n".
        "transport this whole demo is about.\n\n".
        "Fix it either way:\n".
        "  a) move redberry/mailbox-for-laravel from require-dev to require, or\n".
        "  b) set COMPOSER_FLAGS= (empty) in the Vercel project's build env."
    );
}

$step('Package present');

if (getenv('MAILBOX_STORE_DRIVER') === 'database') {
    if (! getenv('MAILBOX_DB_URL') && ! getenv('MAILBOX_DB_HOST')) {
        $fail(
            "MAILBOX_STORE_DRIVER=database but no database is configured.\n\n".
            "Set MAILBOX_DB_URL to your Postgres connection string in the Vercel\n".
            "project's environment variables. A serverless filesystem cannot hold\n".
            "the mailbox: /tmp is not shared between invocations, so a message\n".
            "captured on one request is gone before the dashboard reads it.\n\n".
            'See VERCEL.md.'
        );
    }

    $step('Running mailbox store migrations');

    passthru(
        'php artisan migrate'
        .' --path=vendor/redberry/mailbox-for-laravel/database/migrations'
        .' --realpath --database=mailbox --force 2>&1',
        $exit,
    );

    if ($exit !== 0) {
        $fail('Mailbox store migrations failed. Check MAILBOX_DB_URL.');
    }
}

$step('Build checks passed');
