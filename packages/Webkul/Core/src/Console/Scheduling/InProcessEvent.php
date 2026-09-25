<?php

namespace Webkul\Core\Console\Scheduling;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Console\Kernel;

/**
 * A scheduled artisan command run through the console kernel, in-process,
 * instead of in a spawned shell. See InProcessSchedule for why.
 *
 * Anything that is not an artisan command (a raw exec()) still goes the
 * normal route, since there is no in-process equivalent for it.
 */
class InProcessEvent extends Event
{
    /**
     * Run the command.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return int
     */
    protected function execute($container)
    {
        $artisanCommand = $this->artisanCommand();

        if ($artisanCommand === null) {
            return parent::execute($container);
        }

        return $container->make(Kernel::class)->call($artisanCommand);
    }

    /**
     * Background runs skip finish(), which is what releases the overlap
     * mutex - harmless for a spawned process that cleans up after itself,
     * but an in-process run would hold the lock until it expired.
     *
     * @return $this
     */
    public function runInBackground()
    {
        return $this;
    }

    /**
     * The command line after "artisan", or null when this is not one.
     *
     * Schedule::command() formats as: '/path/php' 'artisan' name --options
     */
    protected function artisanCommand(): ?string
    {
        if (preg_match("/(?:^|\s)'?artisan'?\s+(.+)$/", trim($this->command), $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
