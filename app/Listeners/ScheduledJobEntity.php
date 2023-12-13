<?php

namespace Import\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ScheduledJobEntity extends AbstractListener
{
    public function afterCreateJobsFromScheduledJobs(Event $event): void
    {
        if ($this->getConfig()->get('importJobsMaxDays') !== 0) {
            $this->createJob('Delete Import Jobs', '0 0 * * 0', 'ImportJob', 'deleteOld');
        }
    }
}