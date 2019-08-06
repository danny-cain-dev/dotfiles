<?php

namespace DannyCain\Net\WebSockets\Server;

use DannyCain\Net\Base\IO\StdIO;
use DannyCain\Net\Base\Models\ServerConnection;
use DannyCain\Net\HTTP\Models\HTTPHeader;
use DannyCain\Net\HTTP\Models\HTTPMessage;
use DannyCain\Net\HTTP\Parser\HTTPReader;
use DannyCain\Net\HTTP\Parser\HTTPWriter;
use DannyCain\Net\WebSockets\Models\FrameHeaderModel;
use DannyCain\Net\WebSockets\Models\WebSocketMessageModel;
use DannyCain\Net\WebSockets\Parser\WebSocketReader;
use DannyCain\Net\WebSockets\Parser\WebSocketWriter;

class WebSocketServerConnection extends ServerConnection {
	const STATE_HANDSHAKING = 'handshake';
	const STATE_CONNECTED = 'connected';

	/**
	 * @var \DannyCain\Net\Base\IO\StdIO
	 */
	protected $stream;

	/**
	 * @var \DannyCain\Net\WebSockets\Parser\WebSocketReader
	 */
	protected $reader;

	protected $sendQueue = [];
	protected $controlFrameQueue = [];

	/**
	 * @var \Generator
	 */
	protected $currentWriteThread;
	/**
	 * @var \Generator
	 */
	protected $currentReadThread;

	/**
	 * @var \DannyCain\Net\WebSockets\Parser\WebSocketWriter
	 */
	protected $writer;

	protected $state = self::STATE_HANDSHAKING;

	protected $host = '';
	protected $uri = '';

	public function __construct( $clientID, $socket ) {
		parent::__construct( $clientID, $socket );

		$self = $this;
		$this->stream = new StdIO($this->socket);
		$this->reader = new WebSocketReader($this->stream, function($header, $payload) use($self) {
			/**
			 * @var \DannyCain\Net\WebSockets\Models\FrameHeaderModel $header
			 */
			switch($header->getOpcode()) {
				case FrameHeaderModel::OPCODE_PING:
					$response = new WebSocketMessageModel((function() use($payload) {
						yield $payload;
					})(), FrameHeaderModel::OPCODE_PONG);
					$self->writer->injectControlFrame($response);
					break;
				case FrameHeaderModel::OPCODE_PONG:
					// process pong
					break;
				case FrameHeaderModel::OPCODE_CLOSE:
					$self->close();
					break;
			}
		});

		$this->writer = new WebSocketWriter($this->stream);
		$this->state = self::STATE_HANDSHAKING;
		$this->currentReadThread = $this->readHandshake();
	}

	public function close() {
		fclose($this->socket);
	}

	/**
	 * @param \DannyCain\Net\WebSockets\Models\WebSocketMessageModel $message
	 */
	public function send( $message ) {
		$this->sendQueue[] = $message;
	}

	/**
	 * @return string
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * @return string
	 */
	public function getUri(): string {
		return $this->uri;
	}

	public function readHandshake() {
		yield null;

		$request = null;
		$reader = new HTTPReader($this->stream);
		while($request === null) {
			$request = $reader->read();
		}

		if ($request instanceof \Exception) {
			yield $request;
			return;
		}

		/**
		 * @var HTTPMessage $request
		 */
		$host = $request->getLastHeader("Host")->getValue();
		$upgrade = $request->getLastHeader("Upgrade")->getValue();
		$connection = $request->getLastHeader("Connection")->getValue();
		$secKey = $request->getLastHeader("Sec-WebSocket-Key")->getValue();
		$secVersion = $request->getLastHeader("Sec-WebSocket-Version")->getValue();

		$this->host = $host;
		$this->uri = $request->getUri();

		if (strtolower($upgrade) != 'websocket' || strtolower($connection) != 'upgrade' || $secVersion != '13') {
			// invalid handshake
			// todo - handle this slightly more gracefully (i.e. yield exception and let parent handle)
			fclose($this->socket);
			throw new \RuntimeException("Invalid handshake (Connection = ".$connection.", Upgrade = ".$upgrade.", SecVersion = ".$secVersion);
		}
		yield null;

		$secResponse = base64_encode(sha1($secKey."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
		$response = new HTTPMessage();
		$response->setStatusCode(101);
		$response->setStatusText("Websocket Handshake");
		$response->setHeader(new HTTPHeader("Sec-WebSocket-Accept", $secResponse));
		$response->setHeader(new HTTPHeader("Upgrade", "websocket"));
		$response->setHeader(new HTTPHeader("Connection", "Upgrade"));
		$response->setHeader(new HTTPHeader("Sec-WebSocket-Version", "13"));

		$writer = new HTTPWriter($this->stream);
		$writeProc = $writer->write($response);
		while($writeProc->valid()) {
			yield null;
			$writeProc->next();
		}

		$this->state = self::STATE_CONNECTED;
		$this->currentReadThread = $this->readWebsockets();
	}

	public function readWebsockets() {
		yield null;
		while(!$this->stream->isClosed()) {
			if ( $this->currentWriteThread !== null && $this->currentWriteThread->valid() ) {
				$this->currentWriteThread->next();
				$result = $this->currentWriteThread->current();
				if ( $result !== null ) {
					yield $result;
				}
			}

			if ( count( $this->sendQueue ) && ( $this->currentWriteThread == null || ! $this->currentWriteThread->valid() ) ) {
				$this->currentWriteThread = $this->writer->write( array_shift( $this->sendQueue ) );
			}
			yield $this->reader->read();
		}
	}

	/**
	 * @return \DannyCain\Net\Base\Exceptions\NetException|\DannyCain\Net\WebSockets\Models\WebSocketMessageModel|\Exception|null
	 */
	public function read() {
		$this->currentReadThread->next();
		return $this->currentReadThread->current();
	}
}