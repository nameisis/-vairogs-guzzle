<?php

namespace Vairogs\Utils\Guzzle\Cache\NamingStrategy;

use Psr\Http\Message\RequestInterface;

interface NamingStrategyInterface
{
    public function filename(RequestInterface $request);
}
