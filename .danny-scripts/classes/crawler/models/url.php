<?php

namespace Danny\Scripts\Crawler\Models;

class URL {
	const DEFAULT_PORTS = [
		'http' => 80,
		'https' => 443
	];

	protected $protocol = '';
	protected $domain = '';
	protected $path = '';
	protected $queryString = '';
	protected $port = null;
	protected $fragment = '';

	public function __toString() {
		if ($this->protocol === '')
			$ret = 'http://';
		else
			$ret = $this->protocol.'://';

		$ret .= $this->domain;
		if (!isset(self::DEFAULT_PORTS[$this->protocol]) || self::DEFAULT_PORTS[$this->protocol] != $this->port)
			$ret .= ':'.$this->port;

		$ret .= $this->path;
		if ($this->queryString !== '')
			$ret .= '?'.$this->queryString;

		if ($this->fragment !== '')
			$ret .= '#'.$this->fragment;

		return $ret;
	}

	public static function Parse($uri, ?URL $base = null) {
		if ($uri === null) {
			return null;
		}

		$pos = strpos($uri, '#');
		if ($pos === false)
			$fragment = '';
		else {
			$fragment = substr($uri, $pos + 1);
			$uri = substr($uri, 0, $pos);
		}

		$protocol = $base ? $base->getProtocol() : '';
		$domain = $base ? $base->getDomain() : '';
		$port = null;
		$queryString = '';

		$pos = strpos($uri, ':');
		if ($pos !== false) {
			$protocol = substr($uri, 0, $pos);
			$uri = substr($uri, $pos + 1);

			if (substr($uri, 0, 2) != '//') {
				return null;
			}
		}

		if (substr($uri, 0, 2) == '//') {
			$uri = substr($uri, 2);
			$pos = strpos($uri, '/');
			if ($pos === false) {
				$domain = $uri;
				$uri = '/';
			} else {
				$domain = substr( $uri, 0, $pos);
				$uri = substr($uri, $pos);
			}
		}

		$pos = strpos($domain, ':');
		if ($pos !== false) {
			$port = intval(substr($domain, $pos + 1));
			$domain = substr($domain, 0, $pos);
		} else {
			if (isset(self::DEFAULT_PORTS[$protocol]))
				$port = self::DEFAULT_PORTS[$protocol];
		}

		$pos = strpos($uri, '?');
		if ($pos !== false) {
			$queryString = substr($uri, $pos + 1);
			$uri = substr($uri, 0, $pos);
		}

		if (substr($uri, 0, 1) != '/' && $base !== null) {
			$uri = $base->getBaseDirectory().$uri;
		}

		$ret = new Url();
		$ret->setProtocol($protocol);
		$ret->setDomain($domain);
		$ret->setPort($port);
		$ret->setPath($uri);
		$ret->setFragment($fragment);
		$ret->setQueryString($queryString);

		return $ret;
	}

	public function getBaseDirectory() {
		if (substr($this->path, strlen($this->path) - 1) === '/')
			return $this->path;

		return dirname($this->path).'/';
	}

	/**
	 * @return string
	 */
	public function getFragment(): string {
		return $this->fragment;
	}

	/**
	 * @param string $fragment
	 */
	public function setFragment( string $fragment ): void {
		$this->fragment = $fragment;
	}

	/**
	 * @return string
	 */
	public function getProtocol(): string {
		return $this->protocol;
	}

	/**
	 * @param string $protocol
	 */
	public function setProtocol( string $protocol ): void {
		$this->protocol = $protocol;
	}

	/**
	 * @return string
	 */
	public function getDomain(): string {
		return $this->domain;
	}

	/**
	 * @param string $domain
	 */
	public function setDomain( string $domain ): void {
		$this->domain = $domain;
	}

	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @param string $path
	 */
	public function setPath( string $path ): void {
		$parts = explode('/', $path);
		$canonicalisedPath = [];

		foreach($parts as $part) {
			if ($part == '.')
				continue;
			if ($part == '..')
				array_pop($canonicalisedPath);
			else
				$canonicalisedPath[] = $part;
		}
		$this->path = implode('/', $canonicalisedPath);
	}

	/**
	 * @return string
	 */
	public function getQueryString(): string {
		return $this->queryString;
	}

	/**
	 * @param string $queryString
	 */
	public function setQueryString( string $queryString ): void {
		$this->queryString = $queryString;
	}

	/**
	 * @return null
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @param null $port
	 */
	public function setPort( $port ): void {
		$this->port = $port;
	}
}