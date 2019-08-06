<?php

namespace DannyCain\Net\WebSockets\Parser;

use DannyCain\Net\WebSockets\Models\FrameHeaderModel;
use DannyCain\Net\WebSockets\Models\WebSocketMessageModel;
use DannyCain\Net\WebSockets\Observers\WebsocketsObserver;

class WebSocketReader {

    /**
     * @var \DannyCain\Net\Base\IO\InputStream
     */
    protected $stream;

    /**
     * @var \Generator
     */
    protected $parseInstance;

    protected $expectingContinuation = false;

    /**
     * @var \Closure
     */
    protected $controlFrameHandler;

    /**
     * @var \DannyCain\Net\WebSockets\Observers\WebsocketsObserver
     */
    protected $observer;

    /**
     * WebSocketReader constructor.
     *
     * @param \DannyCain\Net\Base\IO\InputStream $stream
     * @param \Closure|null                      $controlFrameHandler
     */
    public function __construct( \DannyCain\Net\Base\IO\InputStream $stream, \Closure $controlFrameHandler = null ) {
        $this->stream = $stream;
        $this->controlFrameHandler = $controlFrameHandler;
        if ($this->controlFrameHandler === null)
            $this->controlFrameHandler = function() { };

        $this->parseInstance = $this->parseMessage();
    }

    public function observe(WebsocketsObserver $observer) {
        $this->observer = $observer;
    }

    /**
     * @return \DannyCain\Net\WebSockets\Models\WebSocketMessageModel|\Exception
     */
    public function waitForNextMessage() {
        while(!($ret = $this->read())) {
        }
        return $ret;
    }

    protected function readFrameHeader() {
        yield null;

        $header = new FrameHeaderModel();

        // parse header
        $buffer = '';
        while(strlen($buffer) < 2) {
            $buffer .= $this->stream->readBytes(2 - strlen($buffer));
            yield null;
        }

        $byte = ord(substr($buffer, 0, 1));
        $header->setIsFinal( ( $byte & FrameHeaderModel::BITMASK_FIN) == FrameHeaderModel::BITMASK_FIN);
        $header->setReserved(($byte & FrameHeaderModel::BITMASK_RSV) >> 4);
        $header->setOpcode($byte & FrameHeaderModel::BITMASK_OPCODE);

        $byte = ord(substr($buffer, 1,1 ));
        $header->setIsMasked(($byte & FrameHeaderModel::BITMASK_MASK) == FrameHeaderModel::BITMASK_MASK);

        $length = ($byte & ~FrameHeaderModel::BITMASK_MASK);
        if ($length == 126) {
            $expectedBytes = 2;
        } elseif ($length == 127) {
            $expectedBytes = 8;
        } else {
            $header->setLength($length);
            $expectedBytes = 0;
        }

        if ($expectedBytes > 0){
            $length = "";
            while(strlen($length) < $expectedBytes) {
                $length .= $this->stream->readBytes($expectedBytes - strlen($length));
                yield null;
            }
            $header->decodeExtendedLength($length);
        }

        if ($header->isMasked()) {
            $buffer = "";
            while(strlen($buffer) < 4) {
                $buffer .= $this->stream->readBytes(4 - strlen($buffer));
            }
            $header->setMask($buffer);
        }

        yield $header;
    }

    protected function parseMessage() {
        /**
         * @var FrameHeaderModel $header
         */
        $header = null;
        $reader = $this->readFrameHeader();
        while($header === null) {
            yield null;
            $header = $reader->current();
            $reader->next();
        }

        $message = new WebSocketMessageModel();
        $message->setOpcode($header->getOpcode());
        $message->setPayload( $this->readFrameBody( $header ) );
        if (isset($this->observer))
            $this->observer->receive($message);

        if ($header->isControlFrame()) {
            $payload = '';
            foreach($message->getPayload() as $chunk) {
                $payload .= $chunk;
            }
            $this->handleControlFrame($header, $payload);
            $this->parseInstance = $this->parseMessage();
        } else {
            yield $message;
        }
    }

    protected function handleControlFrame(FrameHeaderModel $frame, $payload) {
        call_user_func_array($this->controlFrameHandler, [$frame, $payload]);
    }

    protected function readFrameBody(FrameHeaderModel $header) {
        $nextHeader = $header;
        $header = null;
        /**
         * @var FrameHeaderModel $nextHeader
         * @var FrameHeaderModel $header
         */
        while($header === null || !$header->isFinal()){
            $header = $nextHeader;
            $bytesRead = 0;
            while ($bytesRead < $header->getLength()) {
                $chunkSize = $header->getLength() - $bytesRead;
                if ($chunkSize > 1024) {
                    $chunkSize = 1024;
                }

                $chunk = $this->stream->readBytes($chunkSize);
                if ($header->isMasked()) {
                    $unmasked = "";
                    for ($i = 0; $i < strlen($chunk); $i ++) {
                        $char = substr($chunk, $i, 1);
                        $mask = substr($header->getMask(), ($bytesRead + $i) % 4, 1);
                        $unmasked .= chr(ord($char) ^ ord($mask));
                    }
                    $chunk = $unmasked;
                }
                $bytesRead += strlen($chunk);
                yield $chunk;
            }

            if (!$header->isFinal()) {
                $nextHeader = null;
                $reader = $this->readFrameHeader();
                while($nextHeader === null) {
                    $nextHeader = $reader->current();
                    if ($nextHeader !== null && $nextHeader->isControlFrame()) {
                        $payload = '';
                        while(strlen($payload) < $nextHeader->getLength()) {
                            $payload .= $this->stream->readBytes($nextHeader->getLength() - strlen($payload));
                            yield "";
                        }

                        $this->handleControlFrame($nextHeader, $payload);
                        $nextHeader = null;
                        $reader = $this->readFrameHeader();
                    }

                    $reader->next();
                    yield "";
                }
            }
        }

        $this->parseInstance = $this->parseMessage();
    }

    /**
     * @return \DannyCain\Net\WebSockets\Models\WebSocketMessageModel|\Exception
     */
    public function read() {
        if ($this->parseInstance->valid()) {
            $this->parseInstance->next();
            return $this->parseInstance->current();
        } else {
            return null;
        }
    }
}
