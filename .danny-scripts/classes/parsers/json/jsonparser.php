<?php

namespace Danny\Scripts\Parsers\JSON;

use Danny\Scripts\Parsers\JSON\Exceptions\JSONSyntaxException;
use Danny\Scripts\Parsers\JSON\Listeners\JSONParserListener;
use Danny\Scripts\Parsers\JSON\Models\JSONParserState;

class JSONParser {
	const TYPE_OBJECT = "object";
	const TYPE_ARRAY = "array";

	/**
	 * @var JSONParserState
	 */
	protected $state;

	protected $buffer = '';
	protected $quoted = false;
	protected $escaped = false;
	/**
	 * @var JSONParserListener
	 */
	protected $listener;

	/**
	 * JSONParser constructor.
	 *
	 * @param JSONParserListener $listener
	 */
	public function __construct( JSONParserListener $listener ) {
		$this->listener = $listener;
		$this->state = new JSONParserState();
	}

	public function isComplete() {
		return $this->state->getPath() == [];
	}

	protected function cleanAndValidateBuffer() {
		$ret = trim($this->buffer);

		if ($ret == '')
			return '';

		if (substr($ret, 0, 1) == '"') {
			// todo - better validating?
			if (substr($ret, strlen($ret) - 1) != '"')
				throw new JSONSyntaxException("Invalid string format ".$ret);

			$ret = substr($ret, 1, strlen($ret) - 2);
		} elseif(\is_numeric($ret)) {
			if (strpos($ret, ".") !== false)
				$ret = \floatval($ret);
			else
				$ret = \intval($ret);
		} elseif(\strtolower($ret) == 'true') {
			$ret = true;
		} elseif(\strtolower($ret) == 'false') {
			$ret = false;
		} elseif(\strtolower($ret) == 'null') {
			$ret = null;
		} else {
			throw new JSONSyntaxException("Unrecognised token ".$ret);
		}

		return $ret;
	}

	protected function hasBuffer() {
		return trim($this->buffer) != '';
	}

	public function parse($input) {
		for ($i = 0; $i < strlen($input); $i ++) {
			$character = substr($input, $i, 1);
			if ($this->quoted) {
				if ($this->escaped) {
					$this->buffer .= $character;
					$this->escaped = false;
					continue;
				}

				switch($character) {
					case '"':
						$this->buffer .= $character;
						$this->quoted = false;
						break;
					case '\\';
						$this->buffer .= $character;
						$this->escaped = true;
						break;
					default:
						$this->buffer .= $character;
				}
			} else {
				switch($character) {
					case '"':
						$this->quoted = true;
						$this->buffer .= $character;
						break;
					case ':':
						$this->state->key($this->cleanAndValidateBuffer());
						$this->buffer = '';
						break;
					case ',':
						if ($this->hasBuffer()) {
							$this->state->value($this->cleanAndValidateBuffer());
							$this->buffer = '';
							$this->listener->value($this->state);
						}

						$this->state->next();
						break;
					case '[':
						if ($this->hasBuffer())
							throw new JSONSyntaxException("Unexpected start array (buffer not empty)");

						$this->state->startArray();
						break;
					case '{':
						if ($this->hasBuffer())
							throw new JSONSyntaxException("Unexpected start object (buffer not empty)");

						$this->state->startObject();
						break;
					case '}':
					case ']';
						if ($this->hasBuffer()) {
							$this->state->value($this->cleanAndValidateBuffer());
							$this->buffer = '';
							$this->listener->value($this->state);
						}

						$this->state->end();
						break;
					default:
						$this->buffer .= $character;
						break;
				}
			}
		}
	}
}