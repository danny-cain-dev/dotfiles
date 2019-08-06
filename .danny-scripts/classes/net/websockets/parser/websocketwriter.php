<?php

namespace DannyCain\Net\WebSockets\Parser;

use DannyCain\Net\WebSockets\Models\FrameHeaderModel;
use DannyCain\Net\WebSockets\Models\WebSocketMessageModel;
use DannyCain\Net\WebSockets\Observers\WebsocketsObserver;

class WebSocketWriter {
	protected $mask = false;
	/**
	 * @var \DannyCain\Net\Base\IO\OutputStream
	 */
	protected $stream;

	/**
	 * @var WebSocketMessageModel[]
	 */
	protected $controlFrameQueue = [];

	/**
	 * @var \DannyCain\Net\WebSockets\Observers\WebsocketsObserver
	 */
	protected $observer;

	/**
	 * WebSocketWriter constructor.
	 *
	 * @param bool                                $mask
	 * @param \DannyCain\Net\Base\IO\OutputStream $stream
	 */
	public function __construct( \DannyCain\Net\Base\IO\OutputStream $stream, $mask = false ) {
		$this->stream = $stream;
		$this->mask = $mask;
	}

	public function observe(WebsocketsObserver $observer) {
		$this->observer = $observer;
	}


	protected function writeFrame(FrameHeaderModel $header, $payload) {
		$this->stream->write($header->encode());

		if ($header->isMasked()) {
			$masked = '';
			for ( $i = 0; $i < strlen( $payload ); $i ++ ) {
				$char   = substr( $payload, $i, 1 );
				$mask   = substr( $header->getMask(), $i % 4, 1 );
				$masked .= chr( ord( $char ) ^ ord( $mask ) );
			}
			$payload = $masked;
		}

		$this->stream->write($payload);
	}

	public function injectControlFrame(WebSocketMessageModel $model) {
		$this->controlFrameQueue[] = $model;
	}

	protected function sendControlFrame(WebSocketMessageModel $model) {
		if (isset($this->observer))
			$this->observer->send($model);

		$payload = '';
		foreach($model->getPayload() as $chunk) {
			$payload .= $chunk;
		}

		$header = new FrameHeaderModel();
		$header->setIsFinal(true);
		$header->setLength(strlen($payload));
		$header->setIsMasked($this->mask);
		if ($this->mask) {
			$header->setMask(random_bytes(4));
		}
		$header->setOpcode($model->getOpcode());

		$this->writeFrame($header, $payload);
	}

	/**
	 *
	 * @param \DannyCain\Net\WebSockets\Models\WebSocketMessageModel $message
	 *
	 * @throws \Exception
	 */
	public function writeAndWait(WebSocketMessageModel $message) {
		foreach($this->write($message) as $val) {
			if ($val instanceof \Exception) {
				throw $val;
			}
		}
	}

	/**
	 * @param \DannyCain\Net\WebSockets\Models\WebSocketMessageModel $model
	 *
	 * @return \Generator
	 */
	public function write(WebSocketMessageModel $model) {
		yield;

		if (isset($this->observer))
			$this->observer->send($model);

		$first = true;
		$generator = $model->getPayload();
		while($generator->valid()) {
			yield null;

			while(count($this->controlFrameQueue) > 0) {
				$this->sendControlFrame(array_shift($this->controlFrameQueue));
				yield null;
			}

			$chunk = $generator->current();
			$generator->next();

			$header = new FrameHeaderModel();
			if (!$generator->valid())
				$header->setIsFinal(true);

			if ($first) {
				$header->setOpcode($model->getOpcode());
			} else {
				$header->setOpcode(WebSocketMessageModel::OPCODE_CONTINUATION);
			}
			$header->setLength(strlen($chunk));
			if ($this->mask) {
				$header->setIsMasked(true);
				$header->setMask(\random_bytes(4));
			}

			$this->writeFrame($header, $chunk);
			$first = false;
		}
	}
}