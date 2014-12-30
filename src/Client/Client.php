<?php

namespace Web\Bundle\StatsdBundle\Client;

use Web\Component\Statsd\Client as BaseClient;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class that extends base statsd client, to handle auto-increment from event dispatcher notifications
 *
 */
class Client extends BaseClient
{
    protected $listenedEvents = array();

    /**
     * getter for listenedEvents
     *
     * @return array
     */
    public function getListenedEvents()
    {
        return $this->listenedEvents;
    }

    /**
     * Add an event to listen
     *
     * @param string $eventName   The event name to listen
     * @param array  $eventConfig The event handler configuration
     */
    public function addEventToListen($eventName, $eventConfig)
    {
        $this->listenedEvents[$eventName] = $eventConfig;
    }

    /**
     * Handle an event
     *
     * @param EventInterface $event an event
     */
    public function handleEvent($event)
    {
        $name = $event->getName();
        if (!isset($this->listenedEvents[$name])) {
            return;
        }

        $config        = $this->listenedEvents[$name];
        $immediateSend = false;

        foreach ($config as $conf => $confValue) {
            // increment
            if ('increment' === $conf) {
                $this->increment(self::replaceInNodeFormMethod($event, $confValue));
            } elseif ('count' === $conf) {
                $value = $this->getEventValue($event, 'getValue');
                $this->count(self::replaceInNodeFormMethod($event, $confValue), $value);
            } elseif ('gauge' === $conf) {
                $value = $this->getEventValue($event, 'getValue');
                $this->gauge(self::replaceInNodeFormMethod($event, $confValue), $value);
            } elseif ('set' === $conf) {
                $value = $this->getEventValue($event, 'getValue');
                $this->set(self::replaceInNodeFormMethod($event, $confValue), $value);
            } elseif ('timing' === $conf) {
                $this->addTiming($event, 'getTiming', self::replaceInNodeFormMethod($event, $confValue));
            } elseif (('custom_timing' === $conf) and is_array($confValue)) {
                $this->addTiming($event, $confValue['method'], self::replaceInNodeFormMethod($event, $confValue['node']));
            } elseif ('immediate_send' === $conf) {
                $immediateSend = $confValue;
            } else {
                throw new Exception("configuration : ".$conf." not handled by the StatsdBundle or its value is in a wrong format.");
            }
        }

        if ($immediateSend) {
            $this->send();
        }
    }

    /**
     * getEventValue
     *
     * @param Event  $event
     * @param string $method
     *
     * @return mixed
     */
    private function getEventValue($event, $method)
    {
        if (!method_exists($event, $method)) {
            throw new Exception("The event class ".get_class($event)." must have a ".$method." method in order to mesure value");
        }

        return call_user_func(array($event,$method));
    }

    /**
     * Factorisation of the timing method
     * find the value timed
     *
     * @param object $event        Event
     * @param string $timingMethod Callable method in the event
     * @param string $node         Node
     *
     * @return void
     */
    private function addTiming($event, $timingMethod, $node)
    {
        $timing = $this->getEventValue($event, $timingMethod);
        if ($timing > 0) {
            $this->timing($node, $timing);
        }
    }

    /**
     * Replaces a string with a method name
     *
     * @param EventInterface $event An event
     * @param string         $node  The node in which the replacing will happen
     *
     * @return string
     */
    private static function replaceInNodeFormMethod($event, $node)
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableMagicCall()
            ->getPropertyAccessor();

        if (preg_match_all('/<([^>]*)>/', $node, $matches) > 0) {
            $tokens = $matches[1];
            foreach ($tokens as $token) {
                $value = $propertyAccessor->getValue($event, $token);

                $node = str_replace('<'.$token.'>', $value, $node);
            }
        }

        return $node;
    }
}
