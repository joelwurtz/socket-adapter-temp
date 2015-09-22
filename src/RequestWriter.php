<?php

namespace Http\Socket;

use Psr\Http\Message\RequestInterface;

trait RequestWriter
{
    /**
     * Write a request to a socket
     *
     * @param resource         $socket
     * @param RequestInterface $request
     * @param array            $options
     */
    protected function writeRequest($socket, RequestInterface $request, array $options)
    {

    }

    /**
     * Produce the header of request as a string based on a PSR Request
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function transformRequestHeadersToString(RequestInterface $request)
    {
        $message  = vsprintf('%s %s HTTP/%s', [
            strtoupper($request->getMethod()),
            $request->getUri(),
            $request->getProtocolVersion()
        ])."\r\n";

        foreach ($request->getHeaders() as $name => $values) {
            $message .= $name.': '.implode(', ', $values)."\r\n";
        }

        $message .= "\r\n";

        return $message;
    }
}
 