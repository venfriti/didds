<?php

namespace Webkul\Sitemap\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Webkul\Sitemap\Contracts\Sitemap as SitemapContract;
use Webkul\Sitemap\Jobs\ProcessSitemap;

class SitemapServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(function () {
                app(SitemapContract::class)::all()->each(
                    fn ($sitemap) => ProcessSitemap::dispatch($sitemap)
                );
            })->dailyAt('02:00');
        });
    }
}
