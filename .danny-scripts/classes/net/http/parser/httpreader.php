<?php

namespace DannyCain\Net\HTTP\Parser;

use DannyCain\Net\HTTP\Models\HTTPHeader;
use DannyCain\Net\HTTP\Models\HTTPMessage;

class HTTPReader {

	/**
	 * @var \DannyCain\Net\Base\IO\InputStream
	 */
	protected $stream;

	/**
	 * @var \Generator
	 */
	protected $parseInstance;

	/**
	 * HTTPReader constructor.
	 *
	 * @param \DannyCain\Net\Base\IO\InputStream $stream
	 */
	public function __construct( \DannyCain\Net\Base\IO\InputStream $stream ) {
		$this->stream = $stream;
		$this->parseInstance = $this->parseMessage();
	}

	/**
	 * @return \DannyCain\Net\HTTP\Models\HTTPMessage|\Exception
	 */
	public function waitForNextMessage() {
		while(!($ret = $this->read())) {
		}
		return $ret;
	}

	protected function parseMessage() {
		yield null;

		// parser request / response line
		$line = false;
		while(!$line) {
			$line = $this->stream->readLine();
			yield;
		}

		$message = HTTPMessage::FactoryFromRequestResponseLine($line);
		// parse headers
		while($line !== "") {
			yield;

			$line = $this->stream->readLine();
			if ($line === false) {
				continue;
			}
			$line = trim($line);
			if ($line == '') {
				continue;
			}

			$message->appendHeader(HTTPHeader::Parse($line));
		}

		$contentLength = $message->getLastHeader("Content-Length")->getValue();
		if ($contentLength != "") {
			$message->setContent( $this->readFixedLengthBody( intval( $contentLength ) ) );
			yield $message;
		} elseif($message->getLastHeader("Transfer-Encoding")->getValue() == "chunked") {
			$message->setContent($this->readChunkedBody());
			yield $message;
		} else {
			yield $message;
			$this->parseInstance = $this->parseMessage();
			return;
		}
	}

	protected function readChunkedBody() {
		$reading = true;
		while($reading) {
			while(!($chunkSize = $this->stream->readLine())) {
				yield;
			}

			$chunkSize = base_convert($chunkSize, 16, 10);
			if ($chunkSize == 0) {
				$reading = false;
				continue;
			}

			$bytesRead = 0;
			while($bytesRead < $chunkSize) {
				$length = $chunkSize - $bytesRead;
				if ($length > 1024) {
					$length = 1024;
				}

				$chunk = $this->stream->readBytes($length);
				$bytesRead += strlen($chunk);
				yield $chunk;
			}
			$this->stream->readBytes(2);
		}
		$this->stream->readBytes(2);
		$this->parseInstance = $this->parseMessage();
	}

	protected function readFixedLengthBody($expectedLength) {
		$bytesRead = 0;
		while ($bytesRead < $expectedLength) {
			$chunkSize = $expectedLength - $bytesRead;
			if ($chunkSize > 1024) {
				$chunkSize = 1024;
			}

			$chunk = $this->stream->readBytes($chunkSize);
			$bytesRead += strlen($chunk);

			yield $chunk;
		}
		$this->parseInstance = $this->parseMessage();
	}

	/**
	 * @return HTTPMessage|\Exception
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