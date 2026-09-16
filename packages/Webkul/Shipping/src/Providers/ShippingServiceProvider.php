<?php

namespace Webkul\Shipping\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Shipping\Console\Commands\SyncDhlTracking;
use Webkul\Shipping\Listeners\AutoCreateShipmentListener;
use Webkul\Shipping\Listeners\CancelledOrderShipmentListener;
use Webkul\Shipping\Listeners\DhlShipmentListener;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        include __DIR__.'/../Http/helpers.php';

        $this->registerConfig();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncDhlTracking::class]);
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('sales.shipment.save.after', DhlShipmentListener::class);

        /**
         * Books the DHL waybill as soon as an order is paid, when the
         * store has that setting on. Creating a shipment by hand still
         * works the same way either side of the setting.
         */
        Event::listen('checkout.order.save.after', AutoCreateShipmentListener::class);

        /**
         * Flags a waybill left live by a cancelled order. DHL offers no
         * cancellation endpoint, so this cannot void it - it makes the
         * liability visible on the order instead of silent.
         */
        Event::listen('sales.order.cancel.after', CancelledOrderShipmentListener::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('dhl:sync-tracking')->everyThirtyMinutes()->withoutOverlapping();
        });
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/carriers.php', 'carriers'
        );
    }
}
