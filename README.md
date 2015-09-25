# Socket Adapter for PHP HTTP

The socket adapter use the stream extension from PHP which is integrated into the core

## Features

 * TCP Socket Domain (tcp://hostname:port)
 * UNIX Socket Domain (unix:///path/to/socket.sock)
 * TLS / SSL Encyrption
 * Client Certificate
 * Chunk / Deflate Transfer Encoding
 
## Not supported

 * Gzip / Compress Transfer Encoding
 
## Options

 * remote_socket: Specify the remote socket where the library should send the request to
 
 Can be a tcp remote : tcp://hostname:port
 Can be a unix remote : unix://hostname:port
 
 Do not use tls / ssl scheme are this are handle by the ssl option
 
 * timeout : Timeout for writing request or reading response on the remote
 * ssl : Activate or Desactive the ssl / tls encryption
 * stream_context_options : Custom options for the context of the stream
 
 As an example someone may want to pass a client certificate when using the ssl, a valid configuration for this
 use case would be:
 
 ```
 $options = [
    'stream_context_options' => [
        'ssl' => [
            'local_cert' => '/path/to/my/client-certificate.pem'
        ]
    ]
 ]
 ```

 * stream_context_params : Custom parameters for the context of the stream
 * write_buffer_size : When sending the request we need to bufferize the body, this option specify the size of this buffer
 
