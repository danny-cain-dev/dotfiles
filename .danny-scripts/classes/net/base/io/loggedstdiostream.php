<?php

namespace DannyCain\Net\Base\IO;

class LoggedStdIOStream extends StdIO {
	public static $inputLogPath = '';
	public static $outputLogPath = '';

	protected $inputLogFile = null;
	protected $outputLogFile = null;

	public function __construct( $stream ) {
		parent::__construct( $stream );

		if (self::$inputLogPath !== '') {
			$this->inputLogFile = $this->getPath(self::$inputLogPath, [
				'{type}' => 'in'
			]);

			if (!\file_exists(dirname($this->inputLogFile))) {
				mkdir(dirname($this->inputLogFile), 0777, true);
			}
		}

		if (self::$outputLogPath !== '') {
			$this->outputLogFile = $this->getPath(self::$outputLogPath, [
				'{type}' => 'out'
			]);

			if (!\file_exists(dirname($this->outputLogFile))) {
				mkdir(dirname($this->outputLogFile), 0777, true);
			}
		}
	}

	protected function getPath($templatePath, $extra_params = []) {
		$extra_params['{date}'] = date('Y-m-d-H-i-s');
		return strtr($templatePath, $extra_params);
	}

	function readLine() {
		$ret = parent::readLine();
		if ($this->inputLogFile !== null) {
			\file_put_contents($this->inputLogFile, $ret, \FILE_APPEND);
		}
		return $ret;
	}

	function readBytes( int $bytes ) {
		$ret = parent::readBytes($bytes);
		if ($this->inputLogFile !== null) {
			\file_put_contents($this->inputLogFile, $ret, \FILE_APPEND);
		}
		return $ret;
	}

	function write( $bytes ) {
		if ($this->outputLogFile !== null) {
			\file_put_contents($this->outputLogFile, $bytes, \FILE_APPEND);
		}
		parent::write( $bytes );
	}
}