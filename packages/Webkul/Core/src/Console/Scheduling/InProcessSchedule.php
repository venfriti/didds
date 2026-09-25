<?php

namespace Webkul\Core\Console\Scheduling;

use Illuminate\Console\Scheduling\Schedule;

/**
 * A schedule whose artisan commands run inside the scheduler's own process.
 *
 * Laravel runs every $schedule->command() through Symfony Process, which
 * needs proc_open - and this host disables it. Every scheduled command
 * therefore threw before starting (the log holds thousands of these since
 * August), so DHL tracking never synced and exchange rates never refreshed,
 * while closures like the queue drain, which run in-process, worked.
 *
 * Swapping the schedule rather than each registration covers the core
 * packages' own commands too, without editing them.
 */
class InProcessSchedule extends Schedule
{
    /**
     * Add a new command event to the schedule.
     *
     * @param  string  $command
     * @return \Illuminate\Console\Scheduling\Event
     */
    public function exec($command, array $parameters = [])
    {
        if (count($parameters)) {
            $command .= ' '.$this->compileParameters($parameters);
        }

        $this->events[] = $event = new InProcessEvent($this->eventMutex, $command, $this->timezone);

        $this->mergePendingAttributes($event);

        return $event;
    }
}
