<?php

namespace DannyCain\Net\Base\IO;

class MemoryOutputStream implements OutputStream {
	protected $closed = false;
	protected $buffer = '';

	function close() { $this->closed = true; }

	function getAndFlush() {
		$ret = $this->buffer;
		$this->buffer = '';
		return $ret;
	}

	function isClosed() {
		return $this->closed;
	}

	function write( $bytes ) {
		$this->buffer .= $bytes;
	}
}