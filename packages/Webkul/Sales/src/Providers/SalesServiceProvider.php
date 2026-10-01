<?php

namespace Webkul\Sales\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Sales\Console\Commands\CurrencySelfTest;
use Webkul\Sales\Listeners\OrderCurrencyTripwire;

class SalesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        /**
         * Alarm on any order saved with amounts in more than one currency,
         * whichever payment route created it.
         */
        Event::listen('checkout.order.save.after', OrderCurrencyTripwire::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CurrencySelfTest::class]);
        }

        /**
         * Replay the mixed-currency failure against the live code nightly,
         * at a quiet minute no other scheduled job shares.
         */
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('orders:currency-self-test --notify')->dailyAt('04:15');
        });
    }
}
