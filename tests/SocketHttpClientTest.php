<?php

namespace Http\Socket\Tests;

use Http\Socket\SocketHttpClient;
use Psr\Http\Message\ResponseInterface;

class SocketHttpClientTest extends BaseTestCase
{
    public function testTcpSocketDomain()
    {
        $this->startServer('tcp-server');
        $client   = new SocketHttpClient(['remote_socket' => '127.0.0.1:19999']);
        $response = $client->get('/', []);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnixSocketDomain()
    {
        $this->startServer('unix-domain-server');

        $client   = new SocketHttpClient([
            'remote_socket' => 'unix://'.__DIR__.'/server/server.sock'
        ]);
        $response = $client->get('/', []);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @expectedException \Http\Client\Exception\NetworkException
     */
    public function testNetworkExceptionOnConnectError()
    {
        $client   = new SocketHttpClient(['remote_socket' => '127.0.0.1:19999']);
        $client->get('/', []);
    }

    public function testSslConnection()
    {
        $this->startServer('tcp-ssl-server');

        $client   = new SocketHttpClient([
            'remote_socket' => '127.0.0.1:19999',
            'ssl'           => true,
            'stream_context_options' => [
                'ssl' => [
                    'peer_name' => 'socket-adapter',
                    'cafile' => __DIR__ . '/server/ssl/ca.pem'
                ]
            ]
        ]);
        $response = $client->get('/', []);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSslConnectionWithClientCertificate()
    {
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            $this->markTestSkipped('Test can only run on php 5.6 and superior (for capturing peer certificate)');
        }

        $this->startServer('tcp-ssl-server-client');

        $client   = new SocketHttpClient([
            'remote_socket' => '127.0.0.1:19999',
            'ssl'           => true,
            'stream_context_options' => [
                'ssl' => [
                    'peer_name'  => 'socket-adapter',
                    'cafile'     => __DIR__ . '/server/ssl/ca.pem',
                    'local_cert' => __DIR__ . '/server/ssl/client-and-key.pem'
                ]
            ]
        ]);
        $response = $client->get('/', []);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvalidSslConnectionWithClientCertificate()
    {
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            $this->markTestSkipped('Test can only run on php 5.6 and superior (for capturing peer certificate)');
        }

        $this->startServer('tcp-ssl-server-client');

        $client   = new SocketHttpClient([
            'remote_socket' => '127.0.0.1:19999',
            'ssl'           => true,
            'stream_context_options' => [
                'ssl' => [
                    'peer_name'  => 'socket-adapter',
                    'cafile'     => __DIR__ . '/server/ssl/ca.pem'
                ]
            ]
        ]);
        $response = $client->get('/', []);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @expectedException \Http\Client\Exception\NetworkException
     */
    public function testNetworkExceptionOnSslError()
    {
        $this->startServer('tcp-server');

        $client   = new SocketHttpClient(['remote_socket' => '127.0.0.1:19999', 'ssl' => true]);
        $client->get('/', []);
    }
}
