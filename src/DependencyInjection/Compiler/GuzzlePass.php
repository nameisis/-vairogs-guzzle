<?php

namespace Vairogs\Utils\Guzzle\DependencyInjection\Compiler;

use GuzzleHttp\HandlerStack;
use Vairogs\Utils\DependencyInjection\Component\Definable;
use Vairogs\Utils\VairogsBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class GuzzlePass implements CompilerPassInterface
{
    public const NAME = VairogsBundle::FULL_ALIAS.'.'.Definable::GUZZLE;

    public const MIDDLEWARE_TAG = self::NAME.'.middleware';
    public const CLIENT_TAG = self::NAME.'.client';

    public function process(ContainerBuilder $container): void
    {
        if (VairogsBundle::isEnabled($container, Definable::GUZZLE)) {
            $this->processLoader($container);
            $this->processMiddleware($container);
        }
    }

    private function processLoader(ContainerBuilder $container): void
    {
        $ids = $container->findTaggedServiceIds(self::NAME.'.description_loader');

        if (\count($ids)) {
            $loaders = [];

            foreach ($ids as $id => $options) {
                $loaders[] = new Reference($id);
            }

            $container->findDefinition(self::NAME.'.description_loader.resolver')->setArguments([$loaders]);
        }
    }

    private function processMiddleware(ContainerBuilder $container): void
    {
        $middleware = $this->findAvailableMiddleware($container);
        $this->registerMiddleware($container, $middleware);
    }

    private function findAvailableMiddleware(ContainerBuilder $container): array
    {
        $middleware = [];
        foreach ($container->findTaggedServiceIds(self::MIDDLEWARE_TAG) as $id => $tags) {
            if (\count($tags) > 1) {
                throw new LogicException(\sprintf('Middleware should only use a single \'%s\' tag', self::MIDDLEWARE_TAG));
            }

            if (!isset($tags[0]['alias'])) {
                throw new LogicException(\sprintf('The \'alias\' attribute is mandatory for the \'%s\' tag', self::MIDDLEWARE_TAG));
            }

            $middleware[$tags[0]['priority'] ?? 0][] = [
                'alias' => $tags[0]['alias'],
                'id' => $id,
            ];
        }

        \krsort($middleware);

        return !empty($middleware) ? \array_merge(...$middleware) : [];
    }

    private function registerMiddleware(ContainerBuilder $container, array $middlewareBag): void
    {
        foreach ($container->findTaggedServiceIds(self::CLIENT_TAG) as $clientId => $tags) {
            if (\count($tags) > 1) {
                throw new LogicException(\sprintf('Clients should use a single \'%s\' tag', self::CLIENT_TAG));
            }

            try {
                $clientMiddleware = $this->filterClientMiddleware($middlewareBag, $tags);
            } catch (LogicException $e) {
                continue;
            }

            if (empty($clientMiddleware)) {
                continue;
            }

            $clientDefinition = $container->findDefinition($clientId);

            $arguments = $clientDefinition->getArguments();
            $this->makeOptions($arguments, $options);

            $this->getHandlerStack($options, $container, $handlerStack);
            $this->addMiddlewareToHandlerStack($handlerStack, $clientMiddleware);
            $options['handler'] = $handlerStack;

            \array_unshift($arguments, $options);
            $clientDefinition->setArguments($arguments);
        }
    }

    private function filterClientMiddleware(array $middlewareBag, array $tags): array
    {
        if (!isset($tags[0]['middleware'])) {
            return !empty($middlewareBag) ? $middlewareBag : null;
        }

        $this->makeLists($tags[0], $whiteList, $blackList);

        if ($whiteList) {
            return \array_filter($middlewareBag, function($value) use ($whiteList) {
                return \in_array($value['alias'], $whiteList, true);
            });
        }

        return \array_filter($middlewareBag, function($value) use ($blackList) {
            return !\in_array($value['alias'], $blackList, true);
        });
    }

    private function makeLists($tag, &$whiteList, &$blackList): void
    {
        $whiteList = $blackList = [];
        foreach (\explode(' ', $tag['middleware']) as $middleware) {
            if ('!' === $middleware[0]) {
                $blackList[] = \substr($middleware, 1);
            } else {
                $whiteList[] = $middleware;
            }
        }

        if ($whiteList && $blackList) {
            throw new LogicException('You cannot mix whitelisting and blacklisting of middleware at the same time.');
        }
    }

    private function makeOptions(array $arguments = [], &$options): void
    {
        $options = [];
        if (!empty($arguments)) {
            $options = \array_shift($arguments);
        }
    }

    private function getHandlerStack($options, $container, &$handlerStack): void
    {
        if (!isset($options['handler'])) {
            $handlerStack = new Definition(HandlerStack::class);
            $handlerStack->setFactory([
                HandlerStack::class,
                'create',
            ]);
            $handlerStack->setPublic(false);
        } else {
            $handlerStack = $this->wrapHandlerInHandlerStack($options['handler'], $container);
        }
    }

    private function wrapHandlerInHandlerStack($handler, ContainerBuilder $container): Definition
    {
        if ($handler instanceof Reference) {
            $handler = $container->getDefinition((string)$handler);
        }

        if ($handler instanceof Definition && HandlerStack::class === $handler->getClass()) {
            return $handler;
        }

        $handlerDefinition = new Definition(HandlerStack::class);
        $handlerDefinition->setArguments([$handler]);
        $handlerDefinition->setPublic(false);

        return $handlerDefinition;
    }

    private function addMiddlewareToHandlerStack(Definition $handlerStack, array $middlewareBag): void
    {
        foreach ($middlewareBag as $middleware) {
            $handlerStack->addMethodCall('push', [
                new Reference($middleware['id']),
                $middleware['alias'],
            ]);
        }
    }
}
