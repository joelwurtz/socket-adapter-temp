<?php

namespace Http\Socket\Protocol;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ProtocolInterface
{
    /**
     * Write a request to a socket
     *
     * @param resource         $socket
     * @param RequestInterface $request
     * @param array            $options
     */
    public function writeRequest($socket, RequestInterface $request, array $options = array());

    /**
     * Read a response from a socket
     *
     * @param resource $socket
     * @param array    $options
     *
     * @return ResponseInterface
     */
    public function readResponse($socket, array $options = array());
}
 