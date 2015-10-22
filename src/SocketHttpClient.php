<?php

namespace Http\Socket;

use Http\Client\Exception\NetworkException;
use Http\Client\HttpClient;
use Http\Client\Utils\BatchRequest;
use Http\Message\MessageFactory;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocketHttpClient implements HttpClient
{
    use RequestWriter;
    use ResponseReader;
    use BatchRequest;

    private $config = [
        'remote_socket'          => null,
        'timeout'                => null,
        'stream_context_options' => array(),
        'stream_context_param'   => array(),
        'ssl'                    => null,
        'write_buffer_size'      => 8192,
        'ssl_method'             => STREAM_CRYPTO_METHOD_TLS_CLIENT
    ];

    public function __construct(MessageFactory $messageFactory, array $config = [])
    {
        $this->messageFactory = $messageFactory;
        $this->config = $this->configure($config);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $remote = $this->config['remote_socket'];
        $useSsl = $this->config['ssl'];

        if (null === $remote) {
            $remote = $this->determineRemoteFromRequest($request);
        }

        if (null === $useSsl) {
            $useSsl = ($request->getUri()->getScheme() == "https");
        }

        $socket = $this->createSocket($request, $remote, $useSsl);

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
     * @param boolean          $useSsl  Whether to use ssl or not
     *
     * @throws NetworkException
     *
     * @return resource
     */
    protected function createSocket(RequestInterface $request, $remote, $useSsl)
    {
        $errNo  = null;
        $errMsg = null;

        $socket = @stream_socket_client($remote, $errNo, $errMsg, $this->config['timeout'], STREAM_CLIENT_CONNECT, $this->config['stream_context']);

        if (false === $socket) {
            throw new NetworkException($errMsg, $request);
        }

        if ($useSsl) {
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
        $resolver->setAllowedTypes('ssl', ['bool', 'null']);


        return $resolver->resolve($config);
    }

    /**
     * Return remote socket from the request
     *
     * @param RequestInterface $request
     *
     * @throws NetworkException When no remote can be determined from the request
     *
     * @return string
     */
    private function determineRemoteFromRequest(RequestInterface $request)
    {
        if ($request->getUri()->getHost() == "" && !$request->hasHeader('Host')) {
            throw new NetworkException("Cannot find connection endpoint for this request", $request);
        }

        $host = $request->getUri()->getHost();
        $port = $request->getUri()->getPort() ?: ($request->getUri()->getScheme() == "https" ? 443 : 80);
        $endpoint = sprintf("%s:%s", $host, $port);

        // If use the host header if present for the endpoint
        if (empty($host) && $request->hasHeader('Host')) {
            $endpoint = $request->getHeaderLine('Host');
        }

        return sprintf('tcp://%s', $endpoint);
    }
}
