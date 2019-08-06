<?php

namespace DannyCain\Net\Base\IO;

class MemoryInputStream implements InputStream {
	protected $closed = false;
	protected $buffer = '';

	function close() { $this->closed = true; }
	function write($data) { $this->buffer .= $data; }

	function isClosed() {
		return $this->closed;
	}

	function readLine() {
		$pos = strpos($this->buffer, PHP_EOL);
		if ($pos === false) {
			return false;
		}

		$line = substr($this->buffer, 0, $pos + strlen(PHP_EOL));
		$this->buffer = substr($this->buffer, $pos + strlen(PHP_EOL));
		return $line;
	}

	function readBytes( int $bytes ) {
		if (strlen($this->buffer) < $bytes) {
			return false;
		}

		$ret = substr($this->buffer, 0, $bytes);
		$this->buffer = substr($this->buffer, $bytes);
		return $ret;
	}
}