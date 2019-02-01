<?php

use Danny\Scripts\Parsers\JSON\Exceptions\JSONSyntaxException;
use Danny\Scripts\Parsers\JSON\Models\JSONParserState;

class JSONParserStateTest extends \PHPUnit\Framework\TestCase {
	public function testEnd_OnEmptyState_ThrowsException() {
		$this->expectException(JSONSyntaxException::class);
		$parser = new JSONParserState();
		$parser->end();
	}

	public function testSetKey_OnArray_ThrowsException() {
		$this->expectException(JSONSyntaxException::class);
		$parser = new JSONParserState();
		$parser->startArray();
		$parser->key("");
	}

	public function testEndArray() {
		$parser = new JSONParserState();
		$parser->startArray();
		$parser->startObject();
		$parser->key("Key 1");
		$parser->value("Value 1");
		$parser->next();
		$parser->key("Key 2");
		$parser->value("Value 2");
		$parser->end();
		$parser->end();

		$this->assertEquals([], $parser->getPath());
	}

	public function testEndObject() {
		$parser = new JSONParserState();
		$parser->startArray();
			$parser->startObject();
			$parser->key("Key 1");
			$parser->value("Value 1");
			$parser->next();
			$parser->key("Key 2");
			$parser->value("Value 2");
			$parser->end();

		$this->assertEquals(["0"], $parser->getPath());
	}

	public function testArrayOfObjects() {
		$parser = new JSONParserState();
		$parser->startArray();
			$parser->startObject();
				$parser->key("Key 1");
				$parser->value("Value 1");
				$parser->next();
				$parser->key("Key 2");
				$parser->value("Value 2");

		$this->assertEquals(["0", "Key 2"], $parser->getPath());
	}

	public function testSimpleObject() {
		$parser = new JSONParserState();
		$parser->startObject();

		$parser->key("key 1");
		$parser->value("value 1");
		$parser->next();
		$parser->key("key 2");
		$parser->value("value 2");

		$this->assertEquals(["key 2"], $parser->getPath());
	}

	public function testSimpleArray() {
		$parser = new JSONParserState();
		$parser->startArray();

		$parser->value("value 1");
		$parser->next();
		$parser->value("value 2");

		$this->assertEquals(["1"], $parser->getPath());
	}
}