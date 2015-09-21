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

    public function __construct(array $config = [])
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

    /**
     * Return configuration for the socket adapter
     *
     * @param array $config Configuration from user
     *
     * @return array Configuration resolved
     */
    protected function configure(array $config = [])
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($this->config);

        return $resolver->resolve($config);
    }
}
 