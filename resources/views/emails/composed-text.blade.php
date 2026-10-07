ACME STORE
==========

{{-- Deliberately unescaped: this is the plain-text part, so there is no markup
     for anything to inject into. Blade's {{ }} would turn an apostrophe into
     &#039; and ship HTML entities inside a text/plain body. --}}
{!! $subjectLine !!}
{!! str_repeat('-', min(strlen($subjectLine), 60)) !!}

{!! $body !!}

--
Composed in the Mailbox for Laravel demo. This message was captured locally
and never sent.
