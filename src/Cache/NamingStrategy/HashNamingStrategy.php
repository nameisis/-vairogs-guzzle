<?php

namespace Vairogs\Utils\Guzzle\Cache\NamingStrategy;

use Psr\Http\Message\RequestInterface;

class HashNamingStrategy implements NamingStrategyInterface
{
    public function filename(RequestInterface $request): string
    {
        return \md5(\serialize([
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'headers' => $request->getHeaders(),
        ]));
    }
}
