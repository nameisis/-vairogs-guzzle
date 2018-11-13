<?php

namespace Vairogs\Utils\Guzzle\Cache;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface StorageAdapterInterface
{
    public function fetch(RequestInterface $request): ?ResponseInterface;

    public function save(RequestInterface $request, ResponseInterface $response);
}
