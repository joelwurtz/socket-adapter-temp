<?php

namespace Http\Socket;

use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

trait RequestWriter
{
    protected $filterEncodingMapping = [
        'chunked'  => 'chunk',
        // No encoding
        'identity' => '',
        // LZ77 Compression no support in standard php library
        'gzip'     => null,
        // LZW Compression no support in standard php library
        'compress' => null,
        // Deflate RFC 1950 Supported by the zlib extension in PHP
        'deflate'  => 'zlib.deflate'
    ];

    /**
     * Sanitize header of request to make them consistent
     *
     * @param RequestInterface $request
     * @param array $options
     *
     * @throws \Http\Client\Exception\RequestException
     *
     * @return RequestInterface
     */
    protected function sanitizeRequest(RequestInterface $request, array $options)
    {
        // Deal with body length / chunked
        if ($request->getBody()->getSize() != null) {
            $request = $request->withHeader('Content-Length', $request->getBody()->getSize());
        }

        if (!$request->getBody()->isReadable()) {
            $request = $request->withHeader('Content-Length', 0);
        }

        if (!$request->hasHeader('Content-Length')) {
            if ($request->getProtocolVersion() == '1.0') {
                throw new RequestException('HTTP 1.0 Need to have a content length header for sending body, and no size can be found for the body', $request);
            }

            $values = ["chunked"];

            if ($request->hasHeader('Transfer-encoding')) {
                $values = array_merge($values, $request->getHeader('Transfer-encoding'));
                $values = array_map('strtolower', $values);
                $values = array_unique($values);
            }

            $request = $request->withHeader('Transfer-encoding', $values);
        }

        return $request;
    }

    /**
     * Write a request to a socket
     *
     * @param resource         $socket
     * @param RequestInterface $request
     * @param array            $options
     *
     * @throws \Http\Client\Exception\NetworkException
     */
    protected function writeRequest($socket, RequestInterface $request, array $options)
    {
        if (false === $this->fwrite($socket, $this->transformRequestHeadersToString($request))) {
            throw new NetworkException("Failed to send request, underlying socket not accessible, (BROKEN EPIPE)", $request);
        }

        if ($request->getBody()->isReadable()) {
            $this->writeBody($socket, $request, $options);

            if (false === $this->fwrite($socket, $request->getBody()->getContents())) {
                throw new NetworkException("Failed to send body of the request, underlying socket not accessible, (BROKEN EPIPE)", $request);
            }
        }
    }

    /**
     * Write Body of the request
     *
     * @param resource         $socket
     * @param RequestInterface $request
     * @param array            $options
     *
     * @throws \Http\Client\Exception\NetworkException
     * @throws \Http\Client\Exception\RequestException
     */
    protected function writeBody($socket, RequestInterface $request, array $options)
    {
        // @TODO Handle stream_get_filters
        $filtersApplied   = [];

        if ($request->hasHeader('Transfer-Encoding')) {
            $encodings = $request->getHeader('Transfer-encoding');
            $filters   = [];

            foreach ($encodings as $encoding) {
                if (!isset($this->filterEncodingMapping[$encoding])) {
                    throw new RequestException(sprintf('Transfer encoding %s is not possible', $encoding), $request);
                }

                if (!empty($this->filterEncodingMapping[$encoding])) {
                    $filters[] = $this->filterEncodingMapping[$encoding];
                }
            }

            foreach ($filters as $filter) {
                $filtersApplied[] = stream_filter_prepend($socket, $filter, STREAM_FILTER_READ);
            }
        }

        // @TODO Content Transfer Encoding
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $buffer = $body->read($options['write_buffer_size']);

            if (false === $this->fwrite($socket, $buffer)) {
                throw new NetworkException("Cannot write request body error on socket (BROKEN EPIPE)", $request);
            }
        }

        foreach ($filtersApplied as $filter) {
            stream_filter_remove($filter);
        }
    }

    /**
     * Produce the header of request as a string based on a PSR Request
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function transformRequestHeadersToString(RequestInterface $request)
    {
        $message  = vsprintf('%s %s HTTP/%s', [
            strtoupper($request->getMethod()),
            $request->getUri(),
            $request->getProtocolVersion()
        ])."\r\n";

        foreach ($request->getHeaders() as $name => $values) {
            $message .= $name.': '.implode(', ', $values)."\r\n";
        }

        $message .= "\r\n";

        return $message;
    }

    /**
     * Replace fwrite behavior as api is broken in PHP
     *
     * @see https://secure.phabricator.com/rPHU69490c53c9c2ef2002bc2dd4cecfe9a4b080b497
     *
     * @param resource $stream The stream resource
     * @param string   $bytes  Bytes written in the stream
     *
     * @return bool|int false if pipe is broken, number of bytes written otherwise
     */
    private function fwrite($stream, $bytes)
    {
        if (!strlen($bytes)) {
            return 0;
        }
        $result = @fwrite($stream, $bytes);
        if ($result !== 0) {
            // In cases where some bytes are witten (`$result > 0`) or
            // an error occurs (`$result === false`), the behavior of fwrite() is
            // correct. We can return the value as-is.
            return $result;
        }
        // If we make it here, we performed a 0-length write. Try to distinguish
        // between EAGAIN and EPIPE. To do this, we're going to `stream_select()`
        // the stream, write to it again if PHP claims that it's writable, and
        // consider the pipe broken if the write fails.
        $read = [];
        $write = [$stream];
        $except = [];
        @stream_select($read, $write, $except, 0);
        if (!$write) {
            // The stream isn't writable, so we conclude that it probably really is
            // blocked and the underlying error was EAGAIN. Return 0 to indicate that
            // no data could be written yet.
            return 0;
        }
        // If we make it here, PHP **just** claimed that this stream is writable, so
        // perform a write. If the write also fails, conclude that these failures are
        // EPIPE or some other permanent failure.
        $result = @fwrite($stream, $bytes);
        if ($result !== 0) {
            // The write worked or failed explicitly. This value is fine to return.
            return $result;
        }
        // We performed a 0-length write, were told that the stream was writable, and
        // then immediately performed another 0-length write. Conclude that the pipe
        // is broken and return `false`.
        return false;
    }
}
 