<?php

namespace Vairogs\Utils\Guzzle\Cache\NamingStrategy;

use Psr\Http\Message\RequestInterface;

class SubfolderNamingStrategy extends AbstractNamingStrategy
{
    public function filename(RequestInterface $request): string
    {
        $filename = $request->getUri()->getHost();

        if ('' !== $path = \urldecode(\ltrim($request->getUri()->getPath(), '/'))) {
            $filename .= '/'.$path;
        }

        $filename .= '/'.$request->getMethod();
        $filename .= '_'.$this->getFingerprint($request);

        return $filename;
    }
}
