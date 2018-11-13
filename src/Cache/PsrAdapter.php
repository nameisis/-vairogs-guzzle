<?php

namespace Vairogs\Utils\Guzzle\Cache;

use GuzzleHttp\Psr7\Response;
use Vairogs\Utils\Guzzle\Cache\NamingStrategy\HashNamingStrategy;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PsrAdapter implements StorageAdapterInterface
{
    private $cache;
    private $namingStrategy;
    private $ttl;

    public function __construct(CacheItemPoolInterface $cache, $ttl = 0)
    {
        $this->cache = $cache;
        $this->namingStrategy = new HashNamingStrategy();
        $this->ttl = $ttl;
    }

    public function fetch(RequestInterface $request): ?ResponseInterface
    {
        $key = $this->namingStrategy->filename($request);

        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $data = $item->get();

            return new Response($data['status'], $data['headers'], $data['body'], $data['version'], $data['reason']);
        }

        return null;
    }

    public function save(RequestInterface $request, ResponseInterface $response): void
    {
        $key = $this->namingStrategy->filename($request);

        $item = $this->cache->getItem($key);
        $item->expiresAfter($this->ttl);
        $item->set([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string)$response->getBody(),
            'version' => $response->getProtocolVersion(),
            'reason' => $response->getReasonPhrase(),
        ]);

        $this->cache->save($item);

        $response->getBody()->seek(0);
    }
}
