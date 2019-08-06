<?php

namespace DannyCain\Net\Base;

use DannyCain\Net\Base\Exceptions\NetException;
use DannyCain\Net\Base\Models\ServerConnection;

class Server {
	protected $classConnectionModel = ServerConnection::class;

	protected $certificateFile = '';
	protected $certificatePassPhrase = '';
	protected $certificatePKFile = '';

	protected $address = "0.0.0.0";
	protected $port = 0;
	protected $secure = false;
	protected $listening = false;

	/**
	 * @var \Generator
	 */
	protected $stateHandler;

	/**
	 * @var \Closure
	 */
	protected $callbackConnected;

	/**
	 * @var \Closure
	 */
	protected $callbackMessage;

	/**
	 * @var \Closure
	 */
	protected $callbackClosed;

	/**
	 * @var \Closure
	 */
	protected $callbackError;

	/**
	 * @var resource
	 */
	protected $socket;

	/**
	 * @var ServerConnection[]
	 */
	protected $connections = [];

	protected $nextClientID = 0;

	/**
	 * Server constructor.
	 *
	 * @param string $connectionClass
	 * @param int    $port
	 * @param bool   $secure
	 * @param string $address
	 * @param string $certificate
	 * @param string $certificatePass
	 * @param string $privateKeyFile
	 */
	public function __construct( $connectionClass, int $port, bool $secure, $address = '0.0.0.0', $certificate = '', $certificatePass = '', $privateKeyFile = '' ) {
		$this->classConnectionModel = $connectionClass;
		$this->port   = $port;
		$this->secure = $secure;
		$this->certificateFile = $certificate;
		$this->certificatePassPhrase = $certificatePass;
		$this->address = $address;
		$this->certificatePKFile = $privateKeyFile;

		$this->callbackConnected = function() { };
		$this->callbackError     = function() { };
		$this->callbackMessage   = function() { };
		$this->stateHandler = $this->stateNull();
	}

	public function onError(\Closure $callback) { $this->callbackError = $callback; }
	public function onMessage(\Closure $callback) { $this->callbackMessage = $callback; }
	public function onConnection(\Closure $callback) { $this->callbackConnected = $callback; }
	public function onClose(\Closure $callback) { $this->callbackClosed = $callback; }

	public function close() {
		$this->listening = false;
		if (!is_null($this->socket)) {
			fclose($this->socket);
		}

		foreach($this->connections as $connection) {
			$connection->close();
		}
	}

	public function listen() {
		$context = \stream_context_create();

		if ($this->secure) {
			$pemFile = $this->certificateFile;
			$pemPass = $this->certificatePassPhrase;
			$pkFile = $this->certificatePKFile;

			if(!is_readable($pemFile)) {
				throw new \RuntimeException("SSL Certificate not readable");
			}
			stream_context_set_option($context, 'ssl', 'local_cert', $pemFile);
			stream_context_set_option($context, 'ssl', 'passphrase', $pemPass);
			if ($pkFile !== '') {
				if (!is_readable($pkFile)) {
					throw new \RuntimeException("Private Key not readable");
				}

				stream_context_set_option($context, 'ssl', 'local_pk', $pkFile);
			}
			 $proto = 'tlsv1.2';
		} else {
			$proto = 'tcp';
		}

		$this->socket = stream_socket_server($proto.'://'.$this->address.':'.$this->port, $errNo, $errStr, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN, $context);
		if ($this->socket === false) {
			throw new \RuntimeException($errStr, $errNo);
		}
		$this->listening = true;
	}

	public function tick() {
		$this->stateHandler->next();
		return $this->stateHandler->current();
	}

	protected function stateNull() {
		while(!$this->listening) {
			yield null;
		}

		$this->stateHandler = $this->stateListen();
	}

	protected function get_errors() {
		$ret = [];
		$error = openssl_error_string();
		while($error !== false) {
			$ret[] = $error;
			$error = openssl_error_string();
		}

		return $ret;
	}

	protected function acceptConnection() {
		$read = array($this->socket);
		$write = array();
		if (stream_select($read, $write, $write, 0) == 0) {
			return;
		}

		$this->get_errors();
		$client = stream_socket_accept($read[0], null, $peer_name);

		if (!is_resource($client)) {
			throw new \RuntimeException("Unable to accept connection: ".implode("\n\n", $this->get_errors()));
		}
		stream_set_blocking($client, false);

		/**
		 * @var ServerConnection $connection
		 */
		$connection = new $this->classConnectionModel($this->nextClientID, $client);
		$this->connections[$this->nextClientID] = $connection;
		$this->nextClientID ++;

		\call_user_func_array($this->callbackConnected, [$connection]);
	}

	protected function handleClients() {
		$clients = [];
		$closed = [];

		foreach($this->connections as $clientID => $connection) {
			if ($connection->isClosed()) {
				continue;
			}

			$response = $connection->read();
			if ($response !== null) {
				if ($response instanceof NetException) {
					\call_user_func_array($this->callbackError, [$connection, $response]);
					if ($response->isFatal()) {
						$closed[$clientID] = $connection;
						$connection->close();
						continue;
					}
				} elseif($response instanceof \Exception) {
					\call_user_func_array($this->callbackError, [$connection, $response]);
					$closed[$clientID] = $connection;
					$connection->close();
					continue;
				} else {
					\call_user_func_array($this->callbackMessage, [$connection, $response]);
				}
			}
			$clients[$clientID] = $connection;
		}

		foreach($closed as $clientID => $connection) {
			call_user_func_array($this->callbackClosed, [$connection]);
		}

		$this->connections = $clients;
	}

	public function clientCount() {
		return count($this->connections);
	}

	protected function stateListen() {
		yield;
		while($this->listening) {
			$this->acceptConnection();
			$this->handleClients();
			yield;
		}

		$this->stateHandler = $this->stateNull();
	}
}