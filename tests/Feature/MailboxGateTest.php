<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The dashboard holds password reset links in a real app, so the gate is worth
 * pinning down: open in local, open on the demo host only when explicitly
 * switched on, shut everywhere else.
 */
class MailboxGateTest extends TestCase
{
    public function test_it_opens_in_the_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->assertTrue(Gate::allows('viewMailbox'));
        $this->get('/mailbox')->assertOk();
    }

    public function test_it_is_shut_on_a_non_local_host_by_default(): void
    {
        $this->app['env'] = 'demo';
        config()->set('demo.public_mailbox', false);

        $this->assertFalse(Gate::allows('viewMailbox'));
        $this->get('/mailbox')->assertForbidden();
    }

    public function test_it_opens_on_a_non_local_host_when_deliberately_enabled(): void
    {
        $this->app['env'] = 'demo';
        config()->set('demo.public_mailbox', true);

        $this->assertTrue(Gate::allows('viewMailbox'));
        $this->get('/mailbox')->assertOk();
    }

    public function test_our_definition_wins_over_the_packages_default(): void
    {
        // The package skips its own gate when the ability is already defined.
        // If provider ordering ever changed, this test is how we'd find out.
        $this->app['env'] = 'demo';
        config()->set('demo.public_mailbox', true);

        $this->assertTrue(
            Gate::allows('viewMailbox'),
            'The package default would deny here; our override should allow.'
        );
    }
}
