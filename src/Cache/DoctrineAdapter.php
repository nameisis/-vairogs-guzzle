<?php

namespace Vairogs\Utils\Guzzle\Cache;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Psr7\Response;
use Vairogs\Utils\Guzzle\Cache\NamingStrategy\HashNamingStrategy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DoctrineAdapter implements StorageAdapterInterface
{
    private $cache;
    private $namingStrategy;
    private $ttl;

    public function __construct(Cache $cache, $ttl = 0)
    {
        $this->cache = $cache;
        $this->namingStrategy = new HashNamingStrategy();
        $this->ttl = $ttl;
    }

    public function fetch(RequestInterface $request): ?ResponseInterface
    {
        $key = $this->namingStrategy->filename($request);

        if ($this->cache->contains($key)) {
            $data = $this->cache->fetch($key);

            return new Response($data['status'], $data['headers'], $data['body'], $data['version'], $data['reason']);
        }

        return null;
    }

    public function save(RequestInterface $request, ResponseInterface $response): void
    {
        $data = [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string)$response->getBody(),
            'version' => $response->getProtocolVersion(),
            'reason' => $response->getReasonPhrase(),
        ];

        $this->cache->save($this->namingStrategy->filename($request), $data, $this->ttl);

        $response->getBody()->seek(0);
    }
}
