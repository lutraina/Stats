<?php

namespace Web\Bundle\StatsdBundle\Event;

use Symfony\Component\Console\Event\ConsoleTerminateEvent as BaseEvent;
use Symfony\Component\Console\Event\ConsoleEvent as BaseConsoleEvent;

/**
 * Triggered when console terminate
 */
class ConsoleTerminateEvent extends ConsoleEvent
{
    /**
     * {@inheritDoc}
     */
    protected static function support(BaseConsoleEvent $e)
    {
        return $e instanceof BaseEvent;
    }
}
