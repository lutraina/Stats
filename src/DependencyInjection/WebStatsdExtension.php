<?php

namespace Web\Bundle\StatsdBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class WebStatsdExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);
        $servers       = isset($config['servers']) ? $config['servers'] : [];
        $clients       = isset($config['clients']) ? $config['clients'] : [];

        $clientServiceNames = [];
        foreach ($clients as $alias => $clientConfig) {
            // load client in the container
            $clientServiceNames[] = $this->loadClient(
                $container,
                $alias,
                $clientConfig,
                $servers,
                $config['base_collectors']
            );
        }
        if ($container->getParameter('kernel.debug')) {
            $definition = new Definition('Web\Bundle\StatsdBundle\DataCollector\StatsdDataCollector');

            $definition->setScope(ContainerInterface::SCOPE_CONTAINER);
            $definition->addTag(
                'data_collector',
                [
                    'template' => 'WebStatsdBundle:Collector:statsd',
                    'id' => 'statsd'
                ]
            );

            $definition->addTag(
                'kernel.event_listener',
                [
                    'event' => 'kernel.response',
                    'method' => 'onKernelResponse'
                ]
            );

            foreach ($clientServiceNames as $serviceName) {
                $definition->addMethodCall('addStatsdClient', [$serviceName, new Reference($serviceName)]);
            }

            $container->setDefinition('data_collector.statsd', $definition);
        }

        if ($config['console_events']) {
            $container
                ->register(
                    'listener.statsd.console',
                    'Web\Bundle\StatsdBundle\Listener\ConsoleListener'
                )
                ->addTag(
                    'kernel.event_listener',
                    ['event' => 'console.command', 'method' => 'onCommand']
                )
                ->addTag(
                    'kernel.event_listener',
                    ['event' => 'console.exception', 'method' => 'onException']
                )
                ->addTag(
                    'kernel.event_listener',
                    ['event' => 'console.terminate', 'method' => 'onTerminate']
                )
                ->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')]);
        }
    }

    /**
     * Load a client configuration as a service in the container. A client can use multiple servers
     *
     * @param ContainerInterface $container  The container
     * @param string             $alias      Alias of the client
     * @param array              $config     Base config of the client
     * @param array              $servers    List of available servers as describe in the config file
     * @param boolean            $baseEvents Register base events
     *
     * @throws InvalidConfigurationException
     * @return string the service name
     */
    protected function loadClient($container, $alias, array $config, array $servers, $baseEvents)
    {
        $usedServers    = [];
        $events         = $config['events'];
        $matchedServers = [];

        if ($config['servers'][0] == 'all') {
            // Use all servers
            $matchedServers = array_keys($servers);
        } else {
            // Use only declared servers
            foreach ($config['servers'] as $serverAlias) {

                // Named server
                if (array_key_exists($serverAlias, $servers)) {
                    $matchedServers[] = $serverAlias;
                    continue;
                }

                // Search matchning server config name
                $found = false;
                foreach (array_keys($servers) as $key) {
                    if (fnmatch($serverAlias, $key)) {
                        $matchedServers[] = $key;
                        $found            = true;
                    }
                }

                // No server found
                if (!$found) {
                    throw new InvalidConfigurationException(sprintf(
                        'WebStatsd client %s used server %s which is not defined in the servers section',
                        $alias,
                        $serverAlias
                    ));
                }
            }
        }

        // Matched server congurations
        foreach ($matchedServers as $serverAlias) {
            $usedServers[] = [
                'address' => $servers[$serverAlias]['address'],
                'port'    => $servers[$serverAlias]['port']
            ];
        }

        // Add the statsd client configured
        $serviceId  = ($alias == 'default') ? ‘web_statsd' : ‘web_statsd.'.$alias;
        $definition = new Definition('Web\Bundle\StatsdBundle\Client\Client');
        $definition->setScope(ContainerInterface::SCOPE_CONTAINER);
        $definition->addArgument($usedServers);

        foreach ($events as $eventName => $eventConfig) {
            $definition->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'handleEvent']);
            $definition->addMethodCall('addEventToListen', [$eventName, $eventConfig]);
        }

        $container->setDefinition($serviceId, $definition);

        // Add the statsd client listener
        $serviceListenerId = $serviceId.'.listener';
        $definition = new Definition('Web\Bundle\StatsdBundle\Statsd\Listener');
        $definition->addArgument(new Reference($serviceId));
        $definition->addArgument(new Reference('event_dispatcher'));
        $definition->addTag('kernel.event_listener', [
            'event'    => 'kernel.terminate',
            'method'   => 'onKernelTerminate',
            'priority' => -100
        ]);

        if ($baseEvents) {
            $definition->addTag('kernel.event_listener', [
                'event' => 'kernel.terminate',
                'method' => 'onKernelTerminateEvents',
                'priority' => 0
            ]);
            $definition->addTag('kernel.event_listener', [
                'event' => 'kernel.exception',
                'method' => 'onKernelException',
                'priority' => 0
            ]);
        }
        $container->setDefinition($serviceListenerId, $definition);

        return $serviceId;
    }

    /**
     * select an alias for the extension
     *
     * trick allowing bypassing the Bundle::getContainerExtension check on getAlias
     * not very clean, to investigate
     *
     * @return string
     */
    public function getAlias()
    {
        return ‘web_statsd';
    }
}
