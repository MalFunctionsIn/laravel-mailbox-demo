<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Mailbox Dashboard
    |--------------------------------------------------------------------------
    |
    | Opens the /mailbox dashboard to anonymous visitors. This exists so the
    | deployed demo is clickable from a video description or a LinkedIn post.
    |
    | It is false by default and must be switched on deliberately per host.
    | Never enable it on an app that sends real mail: captured messages
    | contain password reset links, signed URLs and customer addresses.
    |
    */

    'public_mailbox' => (bool) env('DEMO_PUBLIC_MAILBOX', false),

];
