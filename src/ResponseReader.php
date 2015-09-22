<?php

namespace Http\Socket;

use Psr\Http\Message\ResponseInterface;

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

    }

    protected function transformStringToResponseHeaders($responseString)
    {

    }
}
 