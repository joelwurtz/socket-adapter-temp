<?php

namespace Http\Socket;

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    private $socket;

    private $isDetached = false;

    private $size;

    private $readed = 0;

    /**
     * Create the stream
     *
     * @param resource $socket
     * @param integer  $size
     */
    public function __construct($socket, $size = null)
    {
        $this->socket = $socket;
        $this->size   = $size;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return (string)$this->getContents();
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        fclose($this->socket);
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        $this->isDetached = true;

        return $this->socket;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * {@inheritDoc}
     */
    public function tell()
    {
        return ftell($this->socket);
    }

    /**
     * {@inheritDoc}
     */
    public function eof()
    {
        return feof($this->socket);
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException("This stream is not seekable");
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        throw new \RuntimeException("This stream is not seekable");
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function write($string)
    {
        throw new \RuntimeException("This stream is not writable");
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($length)
    {
        if (null === $this->getSize()) {
            return fread($this->socket, $length);
        }

        if ($this->getSize() < ($this->readed + $length)) {
            throw new \RuntimeException("Cannot read more than %s", $this->getSize() - $this->readed);
        }

        // Even if we request a length a non blocking stream can return less data than asked
        $read = fread($this->socket, $length);
        $this->readed += strlen($read);

        return $read;
    }

    /**
     * {@inheritDoc}
     */
    public function getContents()
    {
        if (null === $this->getSize()) {
            return stream_get_contents($this->socket);
        }

        return $this->read($this->getSize() - $this->readed);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata($key = null)
    {
        $meta = stream_get_meta_data($this->socket);

        if (null === $key) {
            return $meta;
        }

        return $meta[$key];
    }
}
 