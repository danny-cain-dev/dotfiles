<?php

namespace DannyCain\Net\WebSockets\Client;

use DannyCain\Net\Base\Exceptions\IOException;
use DannyCain\Net\Base\IO\StdIO;
use DannyCain\Net\HTTP\Models\HTTPHeader;
use DannyCain\Net\HTTP\Models\HTTPMessage;
use DannyCain\Net\HTTP\Parser\HTTPReader;
use DannyCain\Net\HTTP\Parser\HTTPWriter;
use DannyCain\Net\WebSockets\Models\FrameHeaderModel;
use DannyCain\Net\WebSockets\Models\WebSocketMessageModel;
use DannyCain\Net\WebSockets\Observers\WebsocketsObserver;
use DannyCain\Net\WebSockets\Parser\WebSocketReader;
use DannyCain\Net\WebSockets\Parser\WebSocketWriter;
use PHPUnit\Framework\MockObject\Generator;

class WebSocketClientConnection {
    protected $socket = null;

    protected $inputStream;
    protected $outputStream;

    protected $inputStreamClass = StdIO::class;
    protected $outputStreamClass = StdIO::class;


    /**
     * @var \Generator
     */
    protected $reader;

    protected $sending = false;

    /**
     * @var \Generator
     */
    protected $writer;

    /**
     * @var \DannyCain\Net\WebSockets\Observers\WebsocketsObserver
     */
    protected $observer;

    /**
     * @var WebSocketWriter
     */
    protected $websocketsWriter;

    /**
     * @var WebSocketReader
     */
    protected $websocketsReader;

    /**
     * @var \DannyCain\Net\WebSockets\Models\WebSocketMessageModel[]
     */
    protected $sendQueue = [];

    protected $protocol = '';
    protected $domain = '';
    protected $port = 80;
    protected $uri = '';

    protected $callbacks = [];

    /**
     * @param string $inputStreamClass
     */
    public function setInputStreamClass( string $inputStreamClass ): void {
        $this->inputStreamClass = $inputStreamClass;
    }

    /**
     * @param string $outputStreamClass
     */
    public function setOutputStreamClass( string $outputStreamClass ): void {
        $this->outputStreamClass = $outputStreamClass;
    }

    /**
     * @param \DannyCain\Net\WebSockets\Observers\WebsocketsObserver|null $observer
     */
    public function observe($observer) {
        $this->observer = $observer;
        if (isset($this->websocketsReader))
            $this->websocketsReader->observe($observer);
        if (isset($this->websocketsWriter))
            $this->websocketsWriter->observe($observer);
    }

    public function forceClose() {
        fclose($this->socket);
    }

    public function connect($url) {
        if ($this->socket !== null) {
            return false;
        }
        $this->parseURL($url);

        $socket_url = ($this->protocol === 'wss' ? 'ssl' : 'tcp').'://'.$this->domain.':'.$this->port;
        $this->socket = \stream_socket_client($socket_url, $errno, $errstr);
        if (!$this->socket) {
            $this->trigger('error', [
                'connect',
                $errno,
                $errstr
            ]);
            return false;
        }

        \stream_set_blocking($this->socket, false);
        $this->inputStream = new $this->inputStreamClass($this->socket);
        $this->outputStream = new $this->outputStreamClass($this->socket);
        $this->reader = $this->readerHandshake();
        $this->writer = $this->writerWebsockets();

        return true;
    }

    public function pollAndWait() {
        $ret = null;
        while($ret === null && !$this->inputStream->isClosed()) {
            usleep(10);
            $ret = $this->poll();
        }

        return $ret;
    }

    public function poll() {
        if ($this->writer !== null) {
            $this->writer->next();
            $ret = $this->writer->current();
            if ($ret !== null) {
                return $ret;
            }
        }

        if ($this->reader !== null) {
            $this->reader->next();
            $ret = $this->reader->current();
            if ($ret !== null) {
                return $ret;
            }
        }
        return null;
    }

    public function send(WebSocketMessageModel $model) {
        $this->sendQueue[] = $model;
    }

    protected function writerWebsockets() {
        $this->websocketsWriter = new WebSocketWriter($this->outputStream, true);
        if (isset($this->observer))
            $this->websocketsWriter->observe($this->observer);

        try {
            while(!$this->outputStream->isClosed()) {
                yield;

                $message = \array_shift($this->sendQueue);
                if ($message == null) {
                    $this->sending = false;
                    continue;
                }


                $this->sending = true;
                foreach($this->websocketsWriter->write($message) as $result) {
                    yield $result;
                }
            }
        } catch(IOException $e) {
            $this->trigger('error', ['write', $e->getCode(), $e->getMessage()]);
            $this->trigger('close');
        }

    }

    public function isSending() {
        return count($this->sendQueue) > 0 || $this->sending;
    }

    protected function readerHandshake() {
        $parser = new HTTPReader($this->inputStream);
        $writer = new HTTPWriter($this->outputStream);

        $handshake = new HTTPMessage();
        $handshake->setRequestMethod("GET");
        $handshake->setUri($this->uri);
        $handshake->setVersion("HTTP/1.1");

        $secKey = base64_encode(\random_bytes(16));
        $handshake->setHeader(new HTTPHeader("Host", $this->domain));
        $handshake->setHeader(new HTTPHeader("Upgrade", "websocket"));
        $handshake->setHeader(new HTTPHeader("Connection", "upgrade"));
        $handshake->setHeader(new HTTPHeader("Sec-WebSocket-Key", $secKey));
        $handshake->setHeader(new HTTPHeader("Sec-WebSocket-Version", "13"));
        $writer->writeAndWait($handshake);

        $response = null;
        while($response === null) {
            yield;
            $response = $parser->read();
        }

        if (!($response instanceof HTTPMessage)) {
            $this->trigger('error', [
                'handshake',
                '001',
                $response->getMessage(),
                $response
            ]);
            fclose($this->socket);
            return null;
        }

        $expectedSecResponse = \base64_encode(sha1($secKey."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
        if ($response->getStatusCode() != 101) {
            $this->trigger('error', [
                'handshake',
                '002',
                'Handshake failed',
                $response->getStatusText()
            ]);
            fclose($this->socket);
            return null;
        }

        $secResponse = $response->getLastHeader("Sec-WebSocket-Accept")->getValue();
        if ($secResponse != $expectedSecResponse) {
            $this->trigger('error', [
                'handshake',
                '003',
                'Invalid Sec-WebSocket-Accept header'
            ]);
            fclose($this->socket);
            return null;
        }

        $this->trigger('connect');
        return $this->reader = $this->readerWebsockets();
    }

    protected function readerWebsockets() {
        $this->websocketsReader = new WebSocketReader($this->inputStream, function(FrameHeaderModel $header, $payload) {
            $content = '';
            if ($payload instanceof \Iterator) {
                foreach($payload as $chunk)
                    $content .= $chunk;
            }

            switch($header->getOpcode()) {
                case FrameHeaderModel::OPCODE_PING:
                    $this->websocketsWriter->injectControlFrame(new WebSocketMessageModel(function() use($content){
                        yield $content;
                    }, FrameHeaderModel::OPCODE_PONG));
                    break;
                case FrameHeaderModel::OPCODE_PONG:
                    break;
                case FrameHeaderModel::OPCODE_CLOSE:
                    $this->trigger('close', [$content]);
                    break;
            }
        });

        if (isset($this->observer))
            $this->websocketsReader->observe($this->observer);

        while(!$this->inputStream->isClosed()) {
            yield $this->websocketsReader->read();
        }
        $this->trigger('close');
    }

    protected function trigger($event, $arguments = []) {
        if (!isset($this->callbacks[$event]))
            return;

        foreach($this->callbacks[$event] as $callback) {
            \call_user_func_array($callback, $arguments);
        }
    }

    public function on($event, \Closure $callback) {
        if (!isset($this->callbacks[$event]))
            $this->callbacks[$event] = [];
        $this->callbacks[$event][] = $callback;
    }

    protected function parseURL($url) {
        $pos = strpos($url, '://');
        $protocol = substr($url, 0, $pos);
        $url = substr($url, $pos + 3);

        $pos = strpos($url, '/');
        if ($pos === false) {
            $domain = $url;
            $uri = '/';
        } else {
            $domain = substr($url, 0, $pos);
            $uri = substr($url, $pos);
        }

        $pos = strpos($domain, ':');
        if ($pos !== false) {
            $port = intval(substr($domain, $pos + 1));
            $domain = substr($domain, 0, $pos);
        } else {
            switch($protocol) {
                case 'ws':
                    $port = 80;
                    break;
                case 'wss':
                    $port = 443;
                    break;
                default:
                    $port = 80;
                    break;
            }
        }

        $this->protocol = $protocol;
        $this->domain = $domain;
        $this->port = $port;
        $this->uri = $uri;
    }
}
