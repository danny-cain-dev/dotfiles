<?php

namespace DannyCain\Net\HTTP\Server;

use DannyCain\Net\Base\IO\StdIO;
use DannyCain\Net\Base\Models\ServerConnection;
use DannyCain\Net\HTTP\Parser\HTTPReader;
use DannyCain\Net\HTTP\Parser\HTTPWriter;

class HTTPServerConnection extends ServerConnection {

	/**
	 * @var \DannyCain\Net\Base\IO\StdIO
	 */
	protected $stream;

	/**
	 * @var \DannyCain\Net\HTTP\Parser\HTTPReader
	 */
	protected $reader;

	protected $sendQueue = [];
	/**
	 * @var \Generator
	 */
	protected $currentWriteThread;
	/**
	 * @var \DannyCain\Net\HTTP\Parser\HTTPWriter
	 */
	protected $writer;

	public function __construct( $clientID, $socket ) {
		parent::__construct( $clientID, $socket );
		$this->stream = new StdIO($this->socket);
		$this->reader = new HTTPReader($this->stream);
		$this->writer = new HTTPWriter($this->stream);
	}

	/**
	 * @param \DannyCain\Net\HTTP\Models\HTTPMessage $message
	 */
	public function send( $message ) {
		$this->sendQueue[] = $message;
	}

	/**
	 * @return \DannyCain\Net\Base\Exceptions\NetException|\DannyCain\Net\HTTP\Models\HTTPMessage|\Exception|null
	 */
	public function read() {
		if ($this->currentWriteThread !== null && $this->currentWriteThread->valid()) {
			$this->currentWriteThread->next();
			$result = $this->currentWriteThread->current();
			if ($result !== null) {
				return $result;
			}
		}

		if (count($this->sendQueue) && ($this->currentWriteThread == null || !$this->currentWriteThread->valid())) {
			$this->currentWriteThread = $this->writer->write(array_shift($this->sendQueue));
		}

		return $this->reader->read();
	}
}