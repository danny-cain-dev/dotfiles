<?php

namespace Danny\Scripts\Parsers\JSON\Models;

use Danny\Scripts\Parsers\JSON\Exceptions\JSONSyntaxException;
use Danny\Scripts\Parsers\JSON\JSONParser;

class JSONParserModel {
	protected $type = '';
	protected $key = null;
	protected $value = null;
	/**
	 * JSONParserState constructor.
	 *
	 * @param string $type
	 */
	public function __construct( string $type ) {
		$this->type = $type;
		if ($this->isArray())
			$this->key = 0;
	}

	/**
	 * @return string|int
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * @return mixed
	 */
	public function getValue() {
		return $this->value;
	}

	public function isArray() {
		return $this->type === JSONParser::TYPE_ARRAY;
	}

	public function key($key) {
		if ($this->isArray())
			throw new JSONSyntaxException("Unexpected key (array context)");

		$this->key = $key;
	}

	public function value($value) {
		$this->value = $value;
	}

	public function next() {
		if ($this->isArray())
			$this->key ++;
		else {
			$this->key = null;
		}
		$this->value = null;
	}
}