<?php

namespace DannyCain\Net\Base\IO;

interface OutputStream {

	/**
	 * @return bool
	 */
	function isClosed();

	/**
	 * @param string $bytes
	 *
	 * @return void
	 */
	function write($bytes);
}