<?php

namespace Danny\Scripts\Parsers\JSON\Models;

use Danny\Scripts\Parsers\JSON\Exceptions\JSONSyntaxException;
use Danny\Scripts\Parsers\JSON\JSONParser;

class JSONParserState {

	/**
	 * @var JSONParserModel[]
	 */
	protected $stack = array();

	/**
	 * @var JSONParserModel
	 */
	protected $current = null;

	/**
	 * @param bool $includeCurrentKey
	 *
	 * @return array
	 */
	public function getGenerifiedPath($includeCurrentKey = true) {
		$path = array();

		foreach($this->stack as $ancestor) {
			if ($ancestor->isArray())
				$path[] = '[]';
			else
				$path[] = $ancestor->getKey();
		}

		if ($includeCurrentKey && $this->current !== null) {
			if ($this->current->isArray())
				$path[] = '[]';
			else
				$path[] = $this->current->getKey();
		}
		return $path;
	}

	protected function start(JSONParserModel $model) {
		if ($this->current !== null)
			$this->stack[] = $this->current;

		$this->current = $model;
	}

	public function startObject() {
		$this->start(new JSONParserModel(JSONParser::TYPE_OBJECT));
	}

	public function startArray() {
		$this->start(new JSONParserModel(JSONParser::TYPE_ARRAY));
	}

	public function end() {
		if ($this->current === null)
			throw new JSONSyntaxException("Attempting to end empty stack");
		$this->current = array_pop($this->stack);
	}

	public function key($key) {
		$this->current->key($key);
	}

	public function value($value) {
		$this->current->value($value);
	}

	public function next() {
		$this->current->next();
	}

	/**
	 * @param bool $includeCurrentKey
	 *
	 * @return array
	 */
	public function getPath($includeCurrentKey = true) {
		$path = array();

		foreach($this->stack as $ancestor) {
				$path[] = $ancestor->getKey();
		}

		if ($includeCurrentKey && $this->current !== null) {
				$path[] = $this->current->getKey();
		}
		return $path;
	}

	public function getKey() {
		return $this->current->getKey();
	}

	public function getValue() {
		return $this->current->getValue();
	}
}