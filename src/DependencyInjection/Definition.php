<?php

namespace Vairogs\Utils\Guzzle\DependencyInjection;

use Vairogs\Utils\DependencyInjection\Component\Definable;
use Vairogs\Utils\Guzzle\DataCollector\GuzzleCollector;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Vairogs\Utils\VairogsBundle;

class Definition implements Definable
{
    private const ALLOWED = [
        Definable::GUZZLE,
    ];

    public function getExtensionDefinition($extension): ArrayNodeDefinition
    {
        if (!\in_array($extension, self::ALLOWED, true)) {
            throw new InvalidConfigurationException(\sprintf('Invalid extension: %s', $extension));
        }

        $node = (new TreeBuilder())->root(Definable::GUZZLE);
        /** @var ArrayNodeDefinition $node */

        // @formatter:off
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('profiler')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('max_body_size')
                            ->defaultValue(GuzzleCollector::MAX_BODY_SIZE)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('logger')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('service')->defaultValue(null)->end()
                        ->scalarNode('format')
                            ->beforeNormalization()
                                ->ifInArray(['clf', 'debug', 'short'])
                                ->then(function($v) {
                                    return \constant(\sprintf('GuzzleHttp\MessageFormatter::%s', \strtoupper($v)));
                                })
                            ->end()
                            ->defaultValue('clf')
                        ->end()
                        ->scalarNode('level')
                            ->beforeNormalization()
                                ->ifInArray([
                                    'emergency', 'alert', 'critical', 'error',
                                    'warning', 'notice', 'info', 'debug',
                                ])
                                ->then(function($v) {
                                    return \constant(\sprintf('Psr\Log\LogLevel::%s', \strtoupper($v)));
                                })
                            ->end()
                            ->defaultValue('debug')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('redis_client')->defaultValue(null)->end()
                ->booleanNode('autoconfigure')->defaultValue(false)->end()
                ->arrayNode('cache')
                    ->canBeEnabled()
                    ->validate()
                        ->ifTrue(function($v) {
                            return $v['enabled'] && null === $v['adapter'];
                        })
                        ->thenInvalid(\sprintf('The "%s.%s.%s.cache.adapter" key is mandatory if you enable the cache middleware', \VAIROGS, VairogsBundle::ALIAS, Definable::GUZZLE))
                    ->end()
                    ->children()
                        ->scalarNode('adapter')->defaultValue(null)->end()
                        ->booleanNode('disabled')->defaultValue(null)->end()
                    ->end()
                ->end()
                ->arrayNode('mock')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('storage_path')->isRequired()->end()
                        ->scalarNode('mode')->defaultValue('replay')->end()
                        ->arrayNode('request_headers_blacklist')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('response_headers_blacklist')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $node;
    }
}
