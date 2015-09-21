<?php

namespace Http\Socket;

use Http\Client\HttpClient;
use Http\Client\HttpMethods;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocketHttpClient implements HttpClient
{
    use HttpMethods;

    private $config = [
        'remote_socket' => null,
        'timeout'       => null
    ];

    public function __construct($config = array())
    {
        $this->config = $this->configure($config);
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
        $options = isset($options['socket-adapater']) ? $this->configure($options['socket-adapter']) : $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequests(array $requests, array $options = [])
    {
        // TODO: Implement sendRequests() method.
    }

    protected function configure($config)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($this->config);

        return $resolver->resolve($config);
    }
}
 