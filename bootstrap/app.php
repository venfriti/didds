<?php

use App\Http\Middleware\EncryptCookies;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Webkul\Core\Http\Middleware\SecureHeaders;
use Webkul\Installer\Http\Middleware\CanInstall;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /**
         * Remove the default Laravel middleware that prevents requests during maintenance mode. There are three
         * middlewares in the shop that need to be loaded before this middleware. Therefore, we need to remove this
         * middleware from the list and add the overridden middleware at the end of the list.
         *
         * As of now, this has been added in the Admin and Shop providers. I will look for a better approach in Laravel 11 for this.
         */
        $middleware->remove(PreventRequestsDuringMaintenance::class);

        /**
         * Remove the default Laravel middleware that converts empty strings to null. First, handle all nullable cases,
         * then remove this line.
         */
        $middleware->remove(ConvertEmptyStringsToNull::class);

        $middleware->append(SecureHeaders::class);
        $middleware->append(CanInstall::class);

        /**
         * Add the overridden middleware at the end of the list.
         */
        $middleware->replaceInGroup('web', BaseEncryptCookies::class, EncryptCookies::class);

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'paystack/webhook',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule) {
        /**
         * Drains the queue every minute.
         *
         * Mail is queued, and with nothing consuming the queue no order
         * confirmation, shipping notice or invoice ever reached a customer -
         * 220 jobs had accumulated over 34 days before this was added.
         *
         * queue:work --stop-when-empty runs through whatever is waiting and
         * exits, rather than a daemon that would need supervising on shared
         * hosting. --max-time keeps a slow or stuck job from overlapping the
         * next minute's run, and withoutOverlapping is belt and braces.
         */
        /**
         * Refresh GBP and EUR from live FX once a day. USD is pinned in
         * the command itself - naira is the stored price and the dollar
         * figure Paystack charges should not move daily.
         */
        $schedule->command('currency:rates:refresh')->dailyAt('03:30');

        $schedule->call(function () {
            /**
             * Invoked in-process rather than as $schedule->command().
             *
             * The scheduler runs a command by spawning a background process
             * ("... > /dev/null 2>&1 &"), and that spawn silently does
             * nothing on this host - schedule:run reported the job DONE in
             * 14ms without a worker ever starting, so the queue stayed full
             * while queue:work worked perfectly when run by hand.
             *
             * Artisan::call keeps it inside the same PHP process, which the
             * host does allow.
             */
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--tries' => 3,
                '--max-time' => 50,
            ]);
        })
            ->name('drain-queue')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
