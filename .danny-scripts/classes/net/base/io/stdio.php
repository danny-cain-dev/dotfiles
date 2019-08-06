<?php

namespace DannyCain\Net\Base\IO;

use DannyCain\Net\Base\Exceptions\IOException;

class StdIO implements InputStream, OutputStream {
    protected $stream;

    /**
     * StdIO constructor.
     *
     * @param resource $stream
     */
    public function __construct($stream) {
        $this->stream = $stream;
    }

    function isClosed() {
        if ($this->stream === null) {
            return true;
        }

        return !is_resource($this->stream);
    }

    function readLine() {
        if ($this->isClosed()) {
            throw new IOException("Stream closed");
        }

        $ret = fgets($this->stream);

        return $ret;
    }

    function readBytes( $bytes ) {
        if ($this->isClosed()) {
            throw new IOException("Stream closed");
        }

        $ret = fread($this->stream, $bytes);
        return $ret;
    }

    function write( $bytes ) {
        if ($this->isClosed()) {
            throw new IOException("Stream closed");
        }

        if (fwrite($this->stream, $bytes) === false) {
            fclose($this->stream);
            $this->stream = null;
            return false;
        }
        return true;
    }
}
