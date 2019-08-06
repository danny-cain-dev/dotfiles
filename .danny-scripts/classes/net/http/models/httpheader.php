<?php

namespace DannyCain\Net\HTTP\Models;

class HTTPHeader {
	protected $header = "";
	protected $value = "";
	protected $arguments = "";

	/**
	 * HTTPHeader constructor.
	 *
	 * @param string $header
	 * @param string $value
	 * @param string $arguments
	 */
	public function __construct( string $header = '', string $value = '', string $arguments = '') {
		$this->header    = $header;
		$this->value     = $value;
		$this->arguments = $arguments;
	}

	public function __toString() {
		$ret = $this->getHeader().": ".$this->getValue();
		if ($this->getArguments() != '') {
			$ret .= '; '.$this->getArguments();
		}
		return $ret;
	}

	/**
	 * @param $header
	 *
	 * @return \DannyCain\Net\HTTP\Models\HTTPHeader
	 */
	public static function Parse($header) {
		$pos = strpos($header, ":");
		$name = trim(substr($header, 0, $pos));
		$header = trim(substr($header, $pos + 1));

		$pos = strpos($header, ";");
		if ($pos === false) {
			$args = '';
		} else {
			$args = trim(substr($header, $pos + 1));
			$header = trim(substr($header, $pos));
		}

		return new HTTPHeader($name, $header, $args);
	}

	/**
	 * @return string
	 */
	public function getHeader(): string {
		return $this->header;
	}

	/**
	 * @param string $header
	 */
	public function setHeader( string $header ): void {
		$this->header = $header;
	}

	/**
	 * @return string
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * @param string $value
	 */
	public function setValue( string $value ): void {
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function getArguments(): string {
		return $this->arguments;
	}

	/**
	 * @param string $arguments
	 */
	public function setArguments( string $arguments ): void {
		$this->arguments = $arguments;
	}
}