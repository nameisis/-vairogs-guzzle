<?php

namespace Vairogs\Utils\Guzzle\GuzzleHttp\Middleware;

use GuzzleHttp\Promise\RejectedPromise;
use Vairogs\Utils\Guzzle\GuzzleHttp\History;
use Psr\Http\Message\RequestInterface;

class HistoryMiddleware
{
    private $container;

    public function __construct(History $container)
    {
        $this->container = $container;
    }

    public function __invoke(callable $handler)
    {
        return function(RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(function($response) use ($request, $options) {
                $this->container->mergeInfo($request, [
                    'response' => $response,
                    'error' => null,
                    'options' => $options,
                    'info' => [],
                ]);

                return $response;
            }, function($reason) use ($request, $options) {
                $this->container->mergeInfo($request, [
                    'response' => null,
                    'error' => $reason,
                    'options' => $options,
                    'info' => [],
                ]);

                return new RejectedPromise($reason);
            });
        };
    }
}
