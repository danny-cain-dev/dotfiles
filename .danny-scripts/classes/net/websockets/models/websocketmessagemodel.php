<?php

namespace DannyCain\Net\WebSockets\Models;

class WebSocketMessageModel {
	const OPCODE_CONTINUATION = 0b0000;
	const OPCODE_TEXT = 0b0001;
	const OPCODE_BINARY = 0b0010;
	const OPCODE_CLOSE = 0b1000;
	const OPCODE_PING = 0b1001;
	const OPCODE_PONG = 0b1010;

	protected $opcode;

	/**
	 * @var \Generator
	 */
	protected $payload;

	/**
	 * WebSocketMessageModel constructor.
	 *
	 * @param            $opcode
	 * @param \Generator $payload
	 */
	public function __construct( $payload = null, $opcode = self::OPCODE_TEXT) {
		if ($payload instanceof \Closure) {
			$payload = call_user_func($payload);
		}

		$this->opcode  = $opcode;
		$this->payload = $payload;
	}


	/**
	 * @return mixed
	 */
	public function getOpcode() {
		return $this->opcode;
	}

	/**
	 * @param mixed $opcode
	 */
	public function setOpcode( $opcode ) {
		$this->opcode = $opcode;
	}

	public function readAndClonePayload() {
		$buffer = [];
		foreach($this->payload as $chunk) {
			$buffer[] = $chunk;
		}

		$this->payload = (function() use(&$buffer) {
			while(count($buffer) > 0) {
				yield array_shift($buffer);
			}
		})();

		return implode("", $buffer);
	}

	/**
	 * @return \Generator
	 */
	public function getPayload() {
		return $this->payload;
	}

	/**
	 * @param \Generator $payload
	 */
	public function setPayload( $payload ) {
		$this->payload = $payload;
	}
}