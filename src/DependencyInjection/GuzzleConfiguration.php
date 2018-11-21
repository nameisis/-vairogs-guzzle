<?php

namespace Vairogs\Utils\Guzzle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Stopwatch\Stopwatch;
use Vairogs\Utils\DependencyInjection\Component\Configurable;
use Vairogs\Utils\DependencyInjection\Component\Definable;
use Vairogs\Utils\VairogsBundle;

class GuzzleConfiguration implements Configurable
{
    protected $alias;

    public function __construct($alias)
    {
        $this->alias = $alias.'.'.VairogsBundle::ALIAS.'.'.Definable::GUZZLE;
    }

    public function configure(ContainerBuilder $container): void
    {
        $dataCollector = $container->getDefinition($this->alias.'.data_collector.guzzle');
        $dataCollector->replaceArgument(0, $container->getParameter($this->alias.'.profiler')['max_body_size']);

        $debug = $container->getParameter($this->alias.'.profiler')['enabled'];
        if (!$debug || !\class_exists(Stopwatch::class)) {
            $container->removeDefinition($this->alias.'.middleware.stopwatch');
        }

        if (!$debug) {
            $container->removeDefinition($this->alias.'.middleware.history');
            $container->removeDefinition($this->alias.'.data_collector.guzzle');
        }

        $this->processLoggerConfiguration($container->getParameter($this->alias.'.logger'), $container);
        $this->processMockConfiguration($container->getParameter($this->alias.'.mock'), $container, $debug);
        $this->processCacheConfiguration($container->getParameter($this->alias.'.cache'), $container, $debug);
    }

    private function processLoggerConfiguration(array $config, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            $container->removeDefinition($this->alias.'.middleware.logger');
            $container->removeDefinition($this->alias.'.logger.message_formatter');

            return;
        }

        $loggerDefinition = $container->getDefinition($this->alias.'.middleware.logger');

        if ($config['service']) {
            $loggerDefinition->replaceArgument(0, new Reference($config['service']));
        }

        if ($config['format']) {
            $formatterDefinition = $container->getDefinition($this->alias.'.logger.message_formatter');
            $formatterDefinition->replaceArgument(0, $config['format']);
        }

        if ($config['level']) {
            $loggerDefinition->replaceArgument(2, $config['level']);
        }
    }

    private function processMockConfiguration(array $config, ContainerBuilder $container, $debug): void
    {
        if (!$config['enabled']) {
            $container->removeDefinition($this->alias.'.middleware.mock');
            $container->removeDefinition($this->alias.'.mock.storage');

            return;
        }

        $storage = $container->getDefinition($this->alias.'.mock.storage');
        $storage->setArguments([
            $config['storage_path'],
            $config['request_headers_blacklist'],
            $config['response_headers_blacklist'],
        ]);

        $middleware = $container->getDefinition($this->alias.'.middleware.mock');
        $middleware->replaceArgument(1, $config['mode'])->replaceArgument(2, $debug);
    }

    private function processCacheConfiguration(array $config, ContainerBuilder $container, $debug): void
    {
        if (!$config['enabled'] || $config['disabled'] === true) {
            $container->removeDefinition($this->alias.'.middleware.cache');
            $container->removeDefinition($this->alias.'.cache_adapter.redis');

            return;
        }

        $container->getDefinition($this->alias.'.middleware.cache')->addArgument($debug);
        $container->getDefinition($this->alias.'.redis_cache')->replaceArgument(0, new Reference($container->getParameter(\sprintf('%s.redis_client', $this->alias))));

        $container->setAlias($this->alias.'.cache_adapter', $config['adapter']);
    }
}
