<?php

namespace Vairogs\Utils\Guzzle\Cache;

use Vairogs\Utils\Guzzle\Cache\NamingStrategy\NamingStrategyInterface;
use Vairogs\Utils\Guzzle\Cache\NamingStrategy\SubfolderNamingStrategy;
use Vairogs\Utils\Guzzle\GuzzleHttp\Middleware\CacheMiddleware;
use Vairogs\Utils\Guzzle\GuzzleHttp\Middleware\MockMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Filesystem;
use function GuzzleHttp\Psr7\parse_response;
use function GuzzleHttp\Psr7\str;

class MockStorageAdapter implements StorageAdapterInterface
{
    private $storagePath;

    /**
     * @var NamingStrategyInterface[]
     */
    private $namingStrategies = [];

    private $responseHeadersBlacklist = [
        CacheMiddleware::DEBUG_HEADER,
        MockMiddleware::DEBUG_HEADER,
    ];

    public function __construct($storagePath, array $requestHeadersBlacklist = [], array $responseHeadersBlacklist = [])
    {
        $this->storagePath = $storagePath;

        $this->namingStrategies[] = new SubfolderNamingStrategy($requestHeadersBlacklist);

        if (!empty($responseHeadersBlacklist)) {
            $this->responseHeadersBlacklist = $responseHeadersBlacklist;
        }
    }

    public function fetch(RequestInterface $request): ?ResponseInterface
    {
        foreach ($this->namingStrategies as $strategy) {
            if (\file_exists($filename = $this->getFilename($strategy->filename($request)))) {
                return parse_response(\file_get_contents($filename));
            }
        }

        return null;
    }

    private function getFilename($name): string
    {
        return $this->storagePath.'/'.$name.'.txt';
    }

    public function save(RequestInterface $request, ResponseInterface $response): void
    {
        foreach ($this->responseHeadersBlacklist as $header) {
            $response = $response->withoutHeader($header);
        }

        [$strategy] = $this->namingStrategies;

        $filename = $this->getFilename($strategy->filename($request));

        $fileSys = new Filesystem();
        $fileSys->mkdir(\dirname($filename));

        \file_put_contents($filename, str($response));
        $response->getBody()->rewind();
    }
}
