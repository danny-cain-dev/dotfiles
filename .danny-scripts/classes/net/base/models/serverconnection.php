<?php

namespace DannyCain\Net\Base\Models;

abstract class ServerConnection {

	/**
	 * @var resource
	 */
	protected $socket;

	protected $clientID = 0;

	/**
	 * ServerConnection constructor.
	 *
	 * @param          $clientID
	 * @param resource $socket
	 */
	public function __construct( $clientID, $socket ) {
		$this->socket = $socket;
		$this->clientID = $clientID;
	}

	/**
	 * @return int
	 */
	public function getClientID(): int {
		return $this->clientID;
	}

	public function isClosed() {
		return !is_resource($this->socket);
	}

	public function close() {
		fclose($this->socket);
	}

	/**
	 * @param object $message
	 *
	 * @return void
	 */
	public abstract function send($message);

	/**
	 * @return null|\DannyCain\Net\Base\Exceptions\NetException|object
	 */
	public abstract function read();
}