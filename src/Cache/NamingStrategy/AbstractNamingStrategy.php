<?php

namespace Vairogs\Utils\Guzzle\Cache\NamingStrategy;

use Vairogs\Utils\Guzzle\GuzzleHttp\Middleware\CacheMiddleware;
use Psr\Http\Message\RequestInterface;

abstract class AbstractNamingStrategy implements NamingStrategyInterface
{
    private $blacklist = [
        'User-Agent',
        'Host',
        CacheMiddleware::DEBUG_HEADER,
    ];

    public function __construct(array $blacklist = [])
    {
        if (!empty($blacklist)) {
            $this->blacklist = $blacklist;
        }
    }

    protected function getFingerprint(RequestInterface $request): string
    {
        $uri = $request->getUri();

        return \md5(\serialize([
            'method' => $request->getMethod(),
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'user_info' => $uri->getUserInfo(),
            'port' => $uri->getPort(),
            'scheme' => $uri->getScheme(),
            'headers' => \array_diff_key($request->getHeaders(), \array_flip($this->blacklist)),
        ]));
    }
}
