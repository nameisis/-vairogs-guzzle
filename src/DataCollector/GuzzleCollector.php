<?php

namespace Vairogs\Utils\Guzzle\DataCollector;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Vairogs\Utils\DependencyInjection\Component\Definable;
use Vairogs\Utils\Guzzle\GuzzleHttp\History;
use Vairogs\Utils\Guzzle\GuzzleHttp\Middleware\CacheMiddleware;
use Vairogs\Utils\Guzzle\GuzzleHttp\Middleware\MockMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class GuzzleCollector extends DataCollector
{
    public const MAX_BODY_SIZE = 0x10000;

    private $maxBodySize;
    private $history;

    public function __construct($maxBodySize = self::MAX_BODY_SIZE, History $history = null)
    {
        $this->maxBodySize = $maxBodySize;
        $this->history = $history ?: new History();

        $this->data = [];
    }

    public function collect(Request $request, Response $response, Exception $exception = null): void
    {
        foreach ($this->history as $historyRequest) {
            /* @var RequestInterface $historyRequest */
            $transaction = $this->history[$historyRequest];
            /* @var ResponseInterface $historyResponse */
            $historyResponse = $transaction['response'];
            /* @var Exception $error */
            $error = $transaction['error'];
            /* @var array $info */
            $info = $transaction['info'];

            $req = [
                'request' => [
                    'method' => $historyRequest->getMethod(),
                    'version' => $historyRequest->getProtocolVersion(),
                    'headers' => $historyRequest->getHeaders(),
                    'body' => $this->cropContent($historyRequest->getBody()),
                ],
                'info' => $info,
                'uri' => \urldecode($historyRequest->getUri()),
                'httpCode' => 0,
                'error' => null,
            ];

            if ($historyResponse) {
                $this->setReqHistory($req, $historyResponse);
            }

            if ($error && $error instanceof RequestException) {
                $this->setReqError($req, $error);
            }

            $this->data[] = $req;
        }
    }

    private function cropContent(StreamInterface $stream = null): string
    {
        if (null === $stream) {
            return '';
        }

        if ($stream->getSize() <= $this->maxBodySize) {
            return (string)$stream;
        }

        $stream->seek(0);

        return '(partial content)'.$stream->read($this->maxBodySize).'(...)';
    }

    private function setReqHistory(&$req, ResponseInterface $historyResponse): void
    {
        $req['response'] = [
            'reasonPhrase' => $historyResponse->getReasonPhrase(),
            'headers' => $historyResponse->getHeaders(),
            'body' => $this->cropContent($historyResponse->getBody()),
        ];

        $req['httpCode'] = $historyResponse->getStatusCode();

        if ($historyResponse->hasHeader(CacheMiddleware::DEBUG_HEADER)) {
            $req['cache'] = $historyResponse->getHeaderLine(CacheMiddleware::DEBUG_HEADER);
        }

        if ($historyResponse->hasHeader(MockMiddleware::DEBUG_HEADER)) {
            $req['mock'] = $historyResponse->getHeaderLine(MockMiddleware::DEBUG_HEADER);
        }
    }

    private function setReqError(&$req, RequestException $error): void
    {
        $req['error'] = [
            'message' => $error->getMessage(),
            'line' => $error->getLine(),
            'file' => $error->getFile(),
            'code' => $error->getCode(),
            'trace' => $error->getTraceAsString(),
        ];
    }

    public function getName(): string
    {
        return Definable::GUZZLE;
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getErrors(): array
    {
        return \array_filter($this->data, function($call) {
            return 0 === $call['httpCode'] || $call['httpCode'] >= 400;
        });
    }

    public function getTotalTime()
    {
        return \array_sum(\array_map(function($call) {
            return $call['info']['total_time'] ?? 0;
        }, $this->data));
    }

    public function getCalls(): array
    {
        return $this->data;
    }
}
