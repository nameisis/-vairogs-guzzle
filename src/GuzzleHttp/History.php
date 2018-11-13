<?php

namespace Vairogs\Utils\Guzzle\GuzzleHttp;

use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use SplObjectStorage;

class History extends SplObjectStorage
{
    public function addStats(TransferStats $stats): void
    {
        $this->mergeInfo($stats->getRequest(), ['info' => $stats->getHandlerStats()]);
    }

    public function mergeInfo(RequestInterface $request, array $info): void
    {
        $info = \array_merge([
            'response' => null,
            'error' => null,
            'info' => null,
        ], \array_filter($this->contains($request) ? $this[$request] : []), \array_filter($info));

        $this->attach($request, $info);
    }
}
