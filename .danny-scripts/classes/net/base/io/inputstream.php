<?php

namespace DannyCain\Net\Base\IO;

interface InputStream {

    /**
     * @return bool
     */
    function isClosed();

    /**
     * @return string
     * @throws \DannyCain\Net\Base\Exceptions\IOException
     */
    function readLine();

    /**
     * @param int $bytes
     * @throws \DannyCain\Net\Base\Exceptions\IOException
     *
     * @return string
     */
    function readBytes(int $bytes);
}
