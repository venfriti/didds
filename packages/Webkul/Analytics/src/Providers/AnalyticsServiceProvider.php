<?php

namespace Webkul\Analytics\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Webkul\Analytics\Contracts\PageView as PageViewContract;

class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/shop-routes.php');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'analytics');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(function () {
                $query = app(PageViewContract::class)::where('created_at', '<', now()->subDays(180));

                do {
                    $deleted = $query->limit(1000)->delete();
                } while ($deleted > 0);
            })->dailyAt('03:00');
        });
    }
}
