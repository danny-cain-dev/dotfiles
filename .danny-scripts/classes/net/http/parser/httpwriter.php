<?php

namespace DannyCain\Net\HTTP\Parser;

use DannyCain\Net\HTTP\Models\HTTPMessage;

class HTTPWriter {

	/**
	 * @var \DannyCain\Net\Base\IO\OutputStream
	 */
	protected $stream;

	/**
	 * HTTPWriter constructor.
	 *
	 * @param \DannyCain\Net\Base\IO\OutputStream $stream
	 */
	public function __construct( \DannyCain\Net\Base\IO\OutputStream $stream ) { $this->stream = $stream; }

	/**
	 * @param \DannyCain\Net\HTTP\Models\HTTPMessage $message
	 *
	 * @throws \Exception
	 */
	public function writeAndWait(HTTPMessage $message) {
		foreach($this->write($message) as $val) {
			if ($val instanceof \Exception) {
				throw $val;
			}
		}
	}

	public function write(HTTPMessage $message) {
		yield;

		if ($message->isRequest()) {
			$this->stream->write($message->getRequestMethod()." ".$message->getUri()." ".$message->getVersion()."\r\n");
		} else {
			$this->stream->write($message->getVersion()." ".$message->getStatusCode()." ".$message->getStatusText()."\r\n");
		}

		foreach($message->getHeaders() as $headerSets) {
			foreach($headerSets as $header) {
				$this->stream->write(((string)$header)."\r\n");
			}
		}
		$this->stream->write("\r\n");

		$contentLength = $message->getLastHeader("Content-Length")->getValue();
		if ($contentLength !== '') {
			$contentLength = intval($contentLength);
			$bodyWriter = $this->writeFixedLengthBody($contentLength, $message->getContent());
		} elseif($message->getLastHeader("Transfer-Encoding")->getValue() == 'chunked') {
			$bodyWriter = $this->writeChunkedBody($message->getContent());
		} else {
			return;
		}

		foreach($bodyWriter as $result) {
			yield $result;
		}
	}

	protected function writeChunkedBody(\Generator $body) {
		foreach($body as $chunk) {
			yield null;
			if (!$chunk) {
				continue;
			}

			$this->stream->write(base_convert(strlen($chunk), 10, 16)."\r\n");
			$this->stream->write($chunk."\r\n");
		}
		$this->stream->write("0\r\n");
		$this->stream->write("\r\n");
	}

	protected function writeFixedLengthBody($length, \Generator $body) {
		$bytesWritten = 0;
		while($bytesWritten < $length && $body->valid()) {
			$chunk = $body->current();
			if (strlen($chunk) + $bytesWritten > $length) {
				yield new \Exception("Attempting to write too many bytes");
			}

			$bytesWritten += strlen($chunk);
			$this->stream->write($chunk);
			$body->next();
			yield null;
		}

		if ($bytesWritten < $length) {
			yield new \Exception("Not enough bytes written");
		}
	}
}