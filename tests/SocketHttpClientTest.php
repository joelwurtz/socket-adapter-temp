<?php

namespace Http\Socket\Tests;

use Http\Socket\SocketHttpClient;
use Psr\Http\Message\ResponseInterface;

class SocketHttpClientTest extends BaseTestCase
{
    /**
     * @var SocketHttpClient
     */
    protected $client;

    public function setUp()
    {
        $this->client = new SocketHttpClient();
    }

    public function testTcpSocketDomain()
    {
        $this->startServer('tcp-server');

        $response = $this->client->get('/', [], ['socket-adapter' => ['remote_socket' => '127.0.0.1:9999']]);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnixSocketDomain()
    {
        $this->startServer('unix-domain-server');

        $response = $this->client->get('/', [], ['socket-adapter' => [
            'remote_socket' => 'unix://'.__DIR__.'/server/server.sock'
        ]]);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @expectedException \Http\Client\Exception\NetworkException
     */
    public function testNetworkExceptionOnConnectError()
    {
        $this->client->get('/', [], ['socket-adapter' => ['remote_socket' => '127.0.0.1:9999']]);
    }
}
