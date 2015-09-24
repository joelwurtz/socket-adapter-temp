<?php

namespace Http\Socket;

use Http\Client\Exception\BatchException;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\TransferException;
use Http\Client\HttpClient;
use Http\Client\HttpMethods;

use Psr\Http\Message\RequestInterface;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Zend\Diactoros\Request;
use Zend\Diactoros\Stream;

class SocketHttpClient implements HttpClient
{
    use HttpMethods;
    use RequestWriter;
    use ResponseReader;

    private $config = [
        'remote_socket'          => null,
        'timeout'                => null,
        'stream_context_options' => array(),
        'stream_context_param'   => array(),
        'ssl'                    => false,
        'ssl_method'             => STREAM_CRYPTO_METHOD_TLS_CLIENT
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
        if (null === $body) {
            $body = new Stream('php://memory');
        }

        // Create request
        $request = new Request($uri, $method, $body, $headers);
        // Send request
        return $this->sendRequest($request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request, array $options = [])
    {
        $options = isset($options['socket-adapter']) ? $this->configure($options['socket-adapter']) : $this->config;

        if ($options['remote_socket'] === null) {
            $options['remote_socket'] = $this->determineRemoteFromRequest($request);
        }

        $socket = $this->createSocket($request, $options);

        try {
            $this->writeRequest($socket, $request, $options);
            $response = $this->readResponse($socket, $options);
        } catch (\Exception $e) {
            $this->closeSocket($socket);

            throw $e;
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequests(array $requests, array $options = [])
    {
        $responses = [];
        $exceptions = [];

        foreach ($requests as $request) {
            try {
                $responses[] = $this->sendRequest($request, $options);
            } catch (TransferException $e) {
                $exceptions[] = $e;
            }
        }

        if (count($exceptions) > 0) {
            throw new BatchException($exceptions);
        }

        return $responses;
    }

    /**
     * Create the socket to write request and read response on it
     *
     * @param RequestInterface $request Request for
     * @param array            $options Options for creation
     *
     * @throws NetworkException
     *
     * @return resource
     */
    protected function createSocket(RequestInterface $request, array $options)
    {
        $errNo  = null;
        $errMsg = null;

        $socket = @stream_socket_client($options['remote_socket'], $errNo, $errMsg, $options['timeout'], STREAM_CLIENT_CONNECT, $options['stream_context']);

        if (false === $socket) {
            throw new NetworkException($errMsg, $request);
        }

        if ($options['ssl']) {
            if (false === stream_socket_enable_crypto($socket, true, $options['ssl_method'])) {
                throw new NetworkException(sprintf('Cannot enable tls: %s', error_get_last()['message']), $request);
            }
        }

        return $socket;
    }

    protected function closeSocket($socket)
    {
        fclose($socket);
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
        $resolver->setDefault('stream_context', function (Options $options, $previousValue) {
            return stream_context_create($options['stream_context_options'], $options['stream_context_param']);
        });

        $resolver->setDefault('timeout', function (Options $options, $previousValue) {
            if ($previousValue === null) {
                return ini_get('default_socket_timeout');
            }

            return $previousValue;
        });

        $resolver->setAllowedTypes('stream_context_options', 'array');
        $resolver->setAllowedTypes('stream_context_param', 'array');
        $resolver->setAllowedTypes('stream_context', 'resource');
        $resolver->setAllowedTypes('ssl', 'bool');


        return $resolver->resolve($config);
    }

    /**
     * Return remote socket from the request
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    private function determineRemoteFromRequest(RequestInterface $request)
    {
        return sprintf('tcp://%s:%s', $request->getUri()->getHost(), $request->getUri()->getPort() ?: 80);
    }
}
 