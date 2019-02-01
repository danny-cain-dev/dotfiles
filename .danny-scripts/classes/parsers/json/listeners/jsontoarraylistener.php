<?php

namespace Danny\Scripts\Parsers\JSON\Listeners;

use Danny\Scripts\Parsers\JSON\Models\JSONParserState;

class JSONToArrayListener implements JSONParserListener {
	protected $array = array();

	function get() {
		return $this->array;
	}

	function set($array, $keys, $value) {
		$key = array_shift($keys);

		if (count($keys) == 0) {
			$array[$key] = $value;
		} else {
			$array[$key] = $this->set($array[$key], $keys, $value);
		}

		return $array;
	}

	function value( JSONParserState $state ) {
		$this->array = $this->set($this->array, $state->getPath(), $state->getValue());
	}
}