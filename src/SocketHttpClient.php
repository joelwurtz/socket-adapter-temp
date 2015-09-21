<?php

namespace Http\Socket;

use Http\Client\HttpClient;
use Http\Client\HttpMethods;
use Psr\Http\Message\RequestInterface;

class SocketHttpClient implements HttpClient
{
    use HttpMethods;

    private $remoteSocket = null;

    private $timeout;it in

    public function __construct()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function send($method, $uri, array $headers = [], $body = null, array $options = [])
    {
        // Create request

        // Send request
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request, array $options = [])
    {
        // TODO: Implement sendRequest() method.
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequests(array $requests, array $options = [])
    {
        // TODO: Implement sendRequests() method.
    }
}
 