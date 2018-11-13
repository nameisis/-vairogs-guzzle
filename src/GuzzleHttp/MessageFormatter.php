<?php

namespace Vairogs\Utils\Guzzle\GuzzleHttp;

use Exception;
use GuzzleHttp\MessageFormatter as BaseMessageFormatter;
use Vairogs\Utils\Core\Util\Text;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\str;

class MessageFormatter extends BaseMessageFormatter
{
    public $template;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var Exception
     */
    protected $error;

    /**
     * {@inheritdoc}
     */
    public function format(RequestInterface $request, ResponseInterface $response = null, Exception $error = null)
    {
        $cache = [];

        return \preg_replace_callback('/{\s*([A-Za-z_\-\.0-9]+)\s*}/', function(array $matches) use ($request, $response, $error, &$cache) {
            if (isset($cache[$matches[1]])) {
                return $cache[$matches[1]];
            }

            $cache[$matches[1]] = '';

            $method = Text::toCamelCase('parse_'.$matches[1]);
            if (\method_exists($this, $method)) {
                $this->request = $request;
                $this->response = $response;
                $this->error = $error;

                $cache[$matches[1]] = $this->{$method}();
            } elseif (\strpos($matches[1], 'req_header_') === 0) {
                $cache[$matches[1]] = $request->getHeaderLine(\substr($matches[1], 11));
            } elseif (\strpos($matches[1], 'res_header_') === 0) {
                $cache[$matches[1]] = $response ? $response->getHeaderLine(\substr($matches[1], 11)) : 'NULL';
            }

            return $cache[$matches[1]];
        }, $this->template);
    }

    protected function parseRequest(): string
    {
        return str($this->request);
    }

    protected function parseResponse(): string
    {
        return $this->response ? str($this->response) : '';
    }

    protected function parseReqHeaders(): string
    {
        return \trim($this->request->getMethod().' '.(string)$this->request).' HTTP/'.$this->request->getProtocolVersion()."\r\n".$this->headers($this->request);
    }

    private function headers(MessageInterface $message)
    {
        $result = '';
        foreach ($message->getHeaders() as $name => $values) {
            $result .= $name.': '.\implode(', ', $values)."\r\n";
        }

        return \trim($result);
    }

    protected function parseResHeaders(): string
    {
        return $this->response ? \sprintf('HTTP/%s %d %s', $this->response->getProtocolVersion(), $this->response->getStatusCode(), $this->response->getReasonPhrase())."\r\n".$this->headers($this->response) : 'NULL';
    }

    protected function parseReqBody()
    {
        return $this->request->getBody();
    }

    protected function parseResBody()
    {
        return $this->response ? $this->response->getBody() : 'NULL';
    }

    protected function parseDateIso8601()
    {
        return $this->parseTs();
    }

    protected function parseTs()
    {
        return \gmdate('c');
    }

    protected function parseDateCommonLog()
    {
        return \date('d/M/Y:H:i:s O');
    }

    protected function parseMethod(): string
    {
        return $this->request->getMethod();
    }

    protected function parseVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    protected function parseUrl()
    {
        return $this->parseUri();
    }

    protected function parseUri()
    {
        return $this->request->getUri();
    }

    protected function parseTarget(): string
    {
        return $this->request->getRequestTarget();
    }

    protected function parseReqVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    protected function parseResVersion(): string
    {
        return $this->response ? $this->response->getProtocolVersion() : 'NULL';
    }

    protected function parseHost(): string
    {
        return $this->request->getHeaderLine('Host');
    }

    protected function parseHostname(): string
    {
        return \gethostname();
    }

    protected function parseCode()
    {
        return $this->response ? $this->response->getStatusCode() : 'NULL';
    }

    protected function parsePhrase(): string
    {
        return $this->response ? $this->response->getReasonPhrase() : 'NULL';
    }

    protected function parseError(): string
    {
        if ($this->error) {
            return $this->error->getMessage();
        }

        return 'NULL';
    }
}
