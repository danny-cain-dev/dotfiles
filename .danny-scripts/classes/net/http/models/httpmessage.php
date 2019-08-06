<?php

namespace DannyCain\Net\HTTP\Models;

class HTTPMessage {
	protected $version = "HTTP/1.1";
	protected $requestMethod = "";
	protected $uri = "";
	protected $statusCode = 0;
	protected $statusText = "";
	/**
	 * @var \DannyCain\Net\HTTP\Models\HTTPHeader[][]
	 */
	protected $headers = [];

	/**
	 * @var \Generator
	 */
	protected $content;

	public function isRequest() {
		return $this->getRequestMethod() != '' && $this->uri != '';
	}

	public function isResponse() {
		return $this->statusCode > 0 && $this->statusText != '';
	}

	/**
	 * @param $line
	 *
	 * @return \DannyCain\Net\HTTP\Models\HTTPMessage
	 */
	public static function FactoryFromRequestResponseLine($line) {
		$line = trim($line);
		$parts = explode(" ", $line);
		$ret = new HTTPMessage();

		if (substr($line, 0, 5) == 'HTTP/') {
			// response
			$ret->setVersion($parts[0]);
			$ret->setStatusCode(intval($parts[1]));
			$ret->setStatusText(implode(" ", $parts));
		} else {
			// request
			$ret->setRequestMethod($parts[0]);
			$ret->setUri($parts[1]);
			$ret->setVersion($parts[2]);
		}

		return $ret;
	}

	/**
	 * @return \Generator
	 */
	public function getContent(): \Generator {
		return $this->content;
	}

	/**
	 * @param \Generator $content
	 */
	public function setContent( \Generator $content ) {
		$this->content = $content;
	}

	/**
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * @param string $version
	 */
	public function setVersion( string $version ): void {
		$this->version = $version;
	}

	/**
	 * @return string
	 */
	public function getRequestMethod(): string {
		return $this->requestMethod;
	}

	/**
	 * @param string $requestMethod
	 */
	public function setRequestMethod( string $requestMethod ): void {
		$this->requestMethod = $requestMethod;
	}

	/**
	 * @return string
	 */
	public function getUri(): string {
		return $this->uri;
	}

	/**
	 * @param string $uri
	 */
	public function setUri( string $uri ): void {
		$this->uri = $uri;
	}

	/**
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param int $statusCode
	 */
	public function setStatusCode( int $statusCode ): void {
		$this->statusCode = $statusCode;
	}

	/**
	 * @return string
	 */
	public function getStatusText(): string {
		return $this->statusText;
	}

	/**
	 * @param string $statusText
	 */
	public function setStatusText( string $statusText ): void {
		$this->statusText = $statusText;
	}

	public function setHeader(HTTPHeader $header) {
		$this->headers[strtolower($header->getHeader())] = [$header];
	}

	public function appendHeader(HTTPHeader $header) {
		if (!isset($this->headers[strtolower($header->getHeader())])) {
			$this->headers[strtolower($header->getHeader())] = [];
		}
		$this->headers[strtolower($header->getHeader())][] = $header;
	}

	public function getFirstHeader($header) {
		$headers = $this->getAllHeaderValues($header);
		if (count($headers) == 0) {
			return new HTTPHeader($header);
		}

		return $headers[0];
	}

	public function getLastHeader($header) {
		$headers = $this->getAllHeaderValues($header);
		if (count($headers) == 0) {
			return new HTTPHeader($header);
		}

		return $headers[count($headers) - 1];
	}

	public function getAllHeaderValues($header) {
		if (!isset($this->headers[strtolower($header)])) {
			return [];
		}
		return $this->headers[strtolower($header)];
	}

	/**
	 * @return \DannyCain\Net\HTTP\Models\HTTPHeader[][]
	 */
	public function getHeaders() {
		return $this->headers;
	}
}