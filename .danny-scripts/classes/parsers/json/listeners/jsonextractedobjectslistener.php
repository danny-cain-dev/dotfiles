<?php

namespace Danny\Scripts\Parsers\JSON\Listeners;

use Danny\Scripts\Parsers\JSON\Models\JSONParserState;

class JSONExtractedObjectsListener implements JSONParserListener {

	protected $paths   = array();
	protected $current = array();
	/**
	 * @var \Closure
	 */
	protected $callback;

	/**
	 * JSONExtractedObjectsListener constructor.
	 *
	 * @param array $paths
	 */
	public function __construct( array $paths, \Closure $callback ) {
		$this->paths    = $paths;
		$this->callback = $callback;
	}

	protected function set( $array, $keys, $value ) {
		$key = array_shift( $keys );

		if ( count( $keys ) == 0 ) {
			$array[ $key ] = $value;
		} else {
			$array[ $key ] = $this->set( $array[ $key ], $keys, $value );
		}

		return $array;
	}

	function getKeyFromPath( $path ) {
		$parts = array();
		foreach ( $path as $part ) {
			$parts[] = \base64_encode( $part );
//			$parts[] = $part;
		}

		return implode( '/', $parts );
	}

	function getPathFromKey( $key ) {
		$parts = explode( '/', $key );
		$ret   = array();

		foreach ( $parts as $part ) {
			$ret[] = \base64_decode( $part );
//			$ret[] = $part;
		}

		return $ret;
	}

	function processMatch( $matchedPath, JSONParserState $state ) {
		$key = $this->getKeyFromPath( $matchedPath );
		if ( ! isset( $this->current[ $key ] ) ) {
			$this->current[ $key ] = array();
		}

		$relativePath = array_slice( $state->getPath(), count( $matchedPath ) );
		$basePath = array_slice($state->getPath(), 0, count($matchedPath));

		$objectKey    = $this->getKeyFromPath( $basePath );

		if ( ! isset( $this->current[$key][ $objectKey ] ) ) {
			$this->current[$key][ $objectKey ] = array(
				'absolute' => array_slice( $state->getPath(), 0, count( $matchedPath ) ),
				'object'   => array(),
			);
		}

		$this->current[$key][$objectKey]['object'] = $this->set($this->current[$key][$objectKey]['object'], $relativePath, $state->getValue());
	}

	function dispatchObjects( $matchedPath, $data ) {
		$absolute = $data['absolute'];
		$object = $data['object'];
		$this->callback->call($this, $matchedPath, $absolute, $object);
	}

	function doesPathMatch($matchPath, $actualPath) {
		for ($i = 0; $i < count($matchPath); $i ++) {
			if ($matchPath[$i] == '*')
				continue;

			if ($matchPath[$i] == '[]' && \is_numeric($actualPath[$i]))
				continue;

			if ($actualPath[$i] == '[]' && \is_numeric($matchPath[$i]))
				continue;

			if ($matchPath[$i] != $actualPath[$i]) {
				return false;
			}
		}

		return true;
	}

	function dispatchFinishedObjects(JSONParserState $state){
		$currentPath = $state->getPath();

		foreach ( array_keys( $this->current ) as $targetPathKey ) {
			$targetPath = $this->getPathFromKey( $targetPathKey );
			foreach($this->current[$targetPathKey] as $actualPathKey => $object) {
				$actualPath = $this->getPathFromKey($actualPathKey);

				if ($this->doesPathMatch($actualPath, $currentPath))
					continue;

				$this->dispatchObjects($targetPath, $object);
				unset($this->current[$targetPathKey][$actualPathKey]);
			}
		}
	}

	function value( JSONParserState $state ) {
		$currentPath_Generic = $state->getGenerifiedPath();

		foreach ( $this->paths as $targettedPath ) {
			if (!$this->doesPathMatch($targettedPath, $currentPath_Generic)) {
				continue;
			}

			$this->processMatch( $targettedPath, $state );
		}

		$this->dispatchFinishedObjects($state);
	}

	function dispatchByKey($key) {
		foreach ( $this->current[ $key ] as $object ) {
			$this->dispatchObjects( $this->getPathFromKey($key), $object );
		}
		unset( $this->current[ $key ] );
	}

	function finish() {
		foreach(array_keys($this->current) as $key)
			$this->dispatchByKey($key);

		$this->current = array();
	}
}