<?php

namespace Vairogs\Utils\Guzzle\GuzzleHttp\Middleware;

use GuzzleHttp\Promise\FulfilledPromise;
use Vairogs\Utils\Guzzle\Cache\StorageAdapterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CacheMiddleware
{
    public const DEBUG_HEADER = 'X-Guzzle-Cache';
    public const DEBUG_HEADER_HIT = 'HIT';
    public const DEBUG_HEADER_MISS = 'MISS';

    protected $adapter;
    protected $debug;

    public function __construct(StorageAdapterInterface $adapter, $debug = false)
    {
        $this->adapter = $adapter;
        $this->debug = $debug;
    }

    public function __invoke(callable $handler)
    {
        return function(RequestInterface $request, array $options) use ($handler) {
            if (!$response = $this->adapter->fetch($request)) {
                return $this->handleSave($handler, $request, $options);
            }

            $response = $this->addDebugHeader($response, self::DEBUG_HEADER_HIT);

            return new FulfilledPromise($response);
        };
    }

    protected function handleSave(callable $handler, RequestInterface $request, array $options)
    {
        return $handler($request, $options)->then(function(ResponseInterface $response) use ($request) {
            $code = (int)\floor((int)$response->getStatusCode() / 100);
            if ($code === 2) {
                $this->adapter->save($request, $response);
            }

            return $this->addDebugHeader($response, self::DEBUG_HEADER_MISS);
        });
    }

    protected function addDebugHeader(ResponseInterface $response, $value)
    {
        if (!$this->debug) {
            return $response;
        }

        return $response->withHeader(self::DEBUG_HEADER, $value);
    }
}
