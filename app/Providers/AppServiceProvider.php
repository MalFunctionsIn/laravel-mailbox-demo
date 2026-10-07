<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMailboxGate();
    }

    /**
     * Decide who may read the captured mail.
     *
     * The package's own default gate only opens in the "local" environment and
     * denies everything else, which is the right default — captured mail holds
     * password reset links and signed URLs. This override keeps that behaviour
     * and adds one deliberate exception for the deployed demo, where the
     * mailbox only ever contains the fake ACME Store orders.
     *
     * Defining it here also takes it out of the package's hands: the package
     * skips its own definition when the ability is already registered.
     */
    protected function registerMailboxGate(): void
    {
        Gate::define('viewMailbox', function ($user = null): bool {
            if ($this->app->isLocal()) {
                return true;
            }

            return (bool) config('demo.public_mailbox', false);
        });
    }
}
