<?php

namespace Http\Socket;

use Http\Client\Exception\NetworkException;
use Http\Client\HttpClient;
use Http\Client\HttpMethods;
use Http\Client\Util\BatchRequest;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Socket\Filter\Chunk;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocketHttpClient implements HttpClient
{
    use RequestWriter;
    use ResponseReader;
    use HttpMethods;
    use BatchRequest;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    private $config = [
        'remote_socket'          => null,
        'timeout'                => null,
        'stream_context_options' => array(),
        'stream_context_param'   => array(),
        'ssl'                    => false,
        'write_buffer_size'      => 8192,
        'ssl_method'             => STREAM_CRYPTO_METHOD_TLS_CLIENT
    ];

    public function __construct(array $config = [], MessageFactory $messageFactory = null)
    {
        $this->config = $this->configure($config);
        $this->messageFactory = null === $messageFactory ? MessageFactoryDiscovery::find() : $messageFactory;

        stream_filter_register('chunk', Chunk::class);
    }

    /**
     * {@inheritdoc}
     */
    public function send($method, $uri, array $headers = [], $body = null)
    {
        return $this->sendRequest($this->messageFactory->createRequest($method, $uri, '1.1', $headers, $body));
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $remote = $this->config['remote_socket'];

        if (null === $remote) {
            $remote = $this->determineRemoteFromRequest($request);
        }

        $socket = $this->createSocket($request, $remote);

        try {
            $this->writeRequest($socket, $request, $this->config['write_buffer_size']);
            $response = $this->readResponse($socket);
        } catch (\Exception $e) {
            $this->closeSocket($socket);

            throw $e;
        }

        return $response;
    }

    /**
     * Create the socket to write request and read response on it
     *
     * @param RequestInterface $request Request for
     * @param string           $remote  Entrypoint for the connection
     *
     * @throws NetworkException
     *
     * @return resource
     */
    protected function createSocket(RequestInterface $request, $remote)
    {
        $errNo  = null;
        $errMsg = null;

        $socket = @stream_socket_client($remote, $errNo, $errMsg, $this->config['timeout'], STREAM_CLIENT_CONNECT, $this->config['stream_context']);

        if (false === $socket) {
            throw new NetworkException($errMsg, $request);
        }

        if ($this->config['ssl']) {
            if (false === @stream_socket_enable_crypto($socket, true, $this->config['ssl_method'])) {
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
