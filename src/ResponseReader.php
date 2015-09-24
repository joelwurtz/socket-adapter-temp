<?php

namespace Http\Socket;

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

trait ResponseReader
{
    /**
     * Read a response from a socket
     *
     * @param resource $socket
     * @param array    $options
     *
     * @return ResponseInterface
     */
    protected function readResponse($socket, array $options = array())
    {
        $headers = [];

        while (($line = fgets($socket)) !== false) {
            if (rtrim($line) === '') {
                break;
            }
            $headers[] = trim($line);
        }

        $parts = explode(' ', array_shift($headers), 3);

        if (count($parts) <= 1) {
            return null;
        }

        $options = ['protocol_version' => substr($parts[0], -3)];

        if (isset($parts[2])) {
            $options['reason_phrase'] = $parts[2];
        }

        // Set the size on the stream if it was returned in the response
        $responseHeaders = [];

        foreach ($headers as $header) {
            $headerParts = explode(':', $header, 2);
            $responseHeaders[trim($headerParts[0])] = isset($headerParts[1])
                ? trim($headerParts[1])
                : '';
        }

        return new Response(new Stream($socket), $parts[1], $responseHeaders);
    }
}
 