<?php

namespace Web\Bundle\StatsdBundle\Listener;

use Web\Bundle\StatsdBundle\Event\ConsoleEvent;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Event\ConsoleEvent as BaseConsoleEvent;

/**
 * Listen to symfony command events
 * then trigger new custom events
 */
class ConsoleListener
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher = null;

    /**
     * Time when command started
     *
     * @var float
     */
    protected $startTime = null;

    /**
     * Define event dispatch
     *
     * @param EventDispatcherInterface $ev
     */
    public function setEventDispatcher(EventDispatcherInterface $ev)
    {
        $this->eventDispatcher = $ev;
    }

    /**
     * @param BaseConsoleEvent $e
     */
    public function onCommand(BaseConsoleEvent $e)
    {
        $this->startTime = microtime(true);

        $this->dispatch(ConsoleEvent::COMMAND, $e);
    }

    /**
     * @param BaseConsoleEvent $e
     */
    public function onTerminate(BaseConsoleEvent $e)
    {
        $this->dispatch(ConsoleEvent::TERMINATE, $e);
    }

    /**
     * @param BaseConsoleEvent $e
     */
    public function onException(BaseConsoleEvent $e)
    {
        $this->dispatch(ConsoleEvent::EXCEPTION, $e);
    }

    /**
     * Dispatch custom event
     *
     * @param string           $eventName
     * @param BaseConsoleEvent $e
     *
     * @return boolean
     */
    protected function dispatch($eventName, BaseConsoleEvent $e)
    {
        if (!is_null($this->eventDispatcher)) {
            $class = str_replace(
                'Symfony\Component\Console\Event',
                'Web\Bundle\StatsdBundle\Event',
                get_class($e)
            );

            $finaleEvent = $class::createFromConsoleEvent(
                $e,
                $this->startTime,
                !is_null($this->startTime) ? microtime(true) - $this->startTime : null
            );

            return $this->eventDispatcher->dispatch($eventName, $finaleEvent);
        } else {
            return false;
        }
    }
}
