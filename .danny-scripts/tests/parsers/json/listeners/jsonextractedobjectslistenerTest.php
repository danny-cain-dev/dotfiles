<?php

use Danny\Scripts\Parsers\JSON\JSONParser;
use Danny\Scripts\Parsers\JSON\Listeners\JSONExtractedObjectsListener;
use PHPUnit\Framework\TestCase;

class JSONExtractedObjectsListenerTest extends TestCase {

	public function testMatchWildCard() {
		$results = array();

		$listener = new JSONExtractedObjectsListener([
			["[]", "*", "details"]
		], function($targetPath, $actualPath, $match) use (&$results) {
			$results[] = $match;
		});

		$json = json_encode(array(
			"some other value",
			array('person' => array('details' => array('name' => 'Danny'))),
			array('person' => array('details' => array('name' => 'Bob'))),
			array('person' => array('details' => array('name' => 'Dave'))),
			"yet another value",
		));
		$parser = new JSONParser($listener);

		$parser->parse($json);
		$this->assertTrue($parser->isComplete());
		$this->assertEquals([
			['name' => 'Danny'],
			['name' => 'Bob'],
			['name' => 'Dave']
		], $results);
	}

	public function testSimpleExample() {
		$results = array();

		$listener = new JSONExtractedObjectsListener([
			["[]", "person"]
		], function($targetPath, $actualPath, $match) use (&$results) {
			$results[] = $match;
		});

		$json = json_encode(array(
			"some other value",
			array('person' => array('name' => 'Danny')),
			array('person' => array('name' => 'Bob')),
			array('person' => array('name' => 'Dave')),
			"yet another value",
		));
		$parser = new JSONParser($listener);

		$parser->parse($json);
		$this->assertTrue($parser->isComplete());
		$this->assertEquals([
			['name' => 'Danny'],
			['name' => 'Bob'],
			['name' => 'Dave']
		], $results);
	}
}