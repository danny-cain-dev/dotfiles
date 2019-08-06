<?php

namespace DannyCain\Net\WebSockets\Models;

class FrameHeaderModel {
	const BITMASK_FIN    = 0b10000000;
	const BITMASK_RSV    = 0b01110000;
	const BITMASK_OPCODE = 0b00001111;
	const BITMASK_MASK = 0b10000000;
	const BITMASK_PAYLOAD_LENGTH = 0b01111111;

	const OPCODE_CONTINUATION = 0b0000;
	const OPCODE_TEXT = 0b0001;
	const OPCODE_BINARY = 0b0010;
	const OPCODE_CLOSE = 0b1000;
	const OPCODE_PING = 0b1001;
	const OPCODE_PONG = 0b1010;

	const LENGTH_MAX_TWO_BYTE = 65535;
	const LENGTH_MAX_SINGLE_BYTE = 125;

	protected $isFinalised = false;
	protected $reserved = 0;
	protected $opcode = 0;
	protected $isMasked = false;
	protected $mask = "";
	protected $length = 0;

	public function isControlFrame() {
		return in_array($this->opcode, [
			self::OPCODE_CLOSE,
			self::OPCODE_PING,
			self::OPCODE_PONG
		]);
	}

	protected function decimalToBytes($number) {
		$mask = 0b11111111;
		$bytes = "";

		while($number > 0) {
			$byte = $number & $mask;
			$number = $number >> 8;
			$bytes = chr($byte).$bytes;
		}

		return $bytes;
	}

	protected function bytesToDecimal($bytes) {
		// need to check for overflows
		$index = null;
		for ($i = 0; $i < strlen($bytes); $i ++) {
			if ($index !== null && ord($bytes[$i]) != 0)
				$index = $i;
		}

		if (strlen($bytes) - $index >= PHP_INT_SIZE) {
			throw new \RuntimeException("This library does not support unsigned integers [LEN_TOO_LARGE]");
		}

		$val = 0;
		for ($i = 0; $i < strlen($bytes); $i ++) {
			$val = $val << 8;
			$val += ord($bytes[$i]);
		}

		return $val;
	}

	public function decodeExtendedLength($extendedLength) {
		$this->length = $this->bytesToDecimal($extendedLength);
	}

	public function encodeFinRsvAndOpcodeByte() {
		$byte = 0b0;
		if ($this->isFinalised)
			$byte = $byte | self::BITMASK_FIN;
		$byte = $byte | ($this->reserved << 4);
		$byte = $byte | $this->getOpcode();

		return chr($byte);
	}

	public function encodeMaskFlagAndLengthByte() {
		if ($this->getLength() > self::LENGTH_MAX_TWO_BYTE) {
			$byte = 127;
		} elseif($this->getLength() > self::LENGTH_MAX_SINGLE_BYTE) {
			$byte = 126;
		} else {
			$byte = $this->getLength();
		}

		if ($this->isMasked)
			$byte = $byte | self::BITMASK_MASK;

		return chr($byte);
	}

	public function encodeExtendedLengthBytes() {
		if ($this->getLength() <= self::LENGTH_MAX_SINGLE_BYTE)
			return "";

		$lengthBytes = $this->decimalToBytes($this->getLength());
		if ($this->getLength() > self::LENGTH_MAX_TWO_BYTE) {
			$lengthBytes = str_pad($lengthBytes, 8, chr(0b0), STR_PAD_LEFT);
		} else {
			$lengthBytes = str_pad($lengthBytes, 2, chr(0b0), STR_PAD_LEFT);
		}

		return $lengthBytes;
	}

	public function encode() {
		$bytes = "";
		$bytes .= $this->encodeFinRsvAndOpcodeByte();
		$bytes .= $this->encodeMaskFlagAndLengthByte();
		$bytes .= $this->encodeExtendedLengthBytes();

		if ($this->isMasked) {
			$bytes .= $this->getMask();
		}

		return $bytes;
	}

	/**
	 * @return bool
	 */
	public function isFinal(): bool {
		return $this->isFinalised;
	}

	/**
	 * @param bool $isFinalised
	 */
	public function setIsFinal( bool $isFinalised ) {
		$this->isFinalised = $isFinalised;
	}

	/**
	 * @return int
	 */
	public function getReserved(): int {
		return $this->reserved;
	}

	/**
	 * @param int $reserved
	 */
	public function setReserved( int $reserved ) {
		$this->reserved = $reserved;
	}

	/**
	 * @return int
	 */
	public function getOpcode(): int {
		return $this->opcode;
	}

	/**
	 * @param int $opcode
	 */
	public function setOpcode( int $opcode ) {
		$this->opcode = $opcode;
	}

	/**
	 * @return bool
	 */
	public function isMasked(): bool {
		return $this->isMasked;
	}

	/**
	 * @param bool $isMasked
	 */
	public function setIsMasked( bool $isMasked ) {
		$this->isMasked = $isMasked;
	}

	/**
	 * @return string
	 */
	public function getMask(): string {
		return $this->mask;
	}

	/**
	 * @param string $mask
	 */
	public function setMask( string $mask ) {
		$this->mask = $mask;
	}

	/**
	 * @return int
	 */
	public function getLength(): int {
		return $this->length;
	}

	/**
	 * @param int $length
	 */
	public function setLength( int $length ) {
		$this->length = $length;
	}
}