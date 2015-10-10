<?php

namespace Http\Socket;

use Http\Message\MessageFactory;
use Psr\Http\Message\ResponseInterface;

trait ResponseReader
{
    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * Read a response from a socket
     *
     * @param resource $socket
     *
     * @return ResponseInterface
     */
    protected function readResponse($socket)
    {
        $headers  = [];
        $reason   = null;
        $status   = null;
        $protocol = null;

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

        $protocol = substr($parts[0], -3);
        $status   = $parts[1];

        if (isset($parts[2])) {
            $reason = $parts[2];
        }

        // Set the size on the stream if it was returned in the response
        $responseHeaders = [];

        foreach ($headers as $header) {
            $headerParts = explode(':', $header, 2);
            $responseHeaders[trim($headerParts[0])] = isset($headerParts[1])
                ? trim($headerParts[1])
                : '';
        }

        return $this->messageFactory->createResponse($status, $reason, $protocol, $headers, $socket);
    }
}
