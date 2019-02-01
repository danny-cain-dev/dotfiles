<?php

use Danny\Scripts\Parsers\JSON\JSONParser;
use Danny\Scripts\Parsers\JSON\Listeners\JSONToArrayListener;
use PHPUnit\Framework\TestCase;

class JSONParserTest extends TestCase {
    public function testSimpleArray() {
        $listener = new JSONToArrayListener();
        $parser = new JSONParser($listener);

        $parser->parse('["value 1", "value 2"]');

        $this->assertTrue($parser->isComplete());
        $this->assertEquals(["value 1", "value 2"], $listener->get());
    }

    public function testSimpleObject() {
        $listener = new JSONToArrayListener();
        $parser = new JSONParser($listener);

        $parser->parse('{"key" : "value", "key 2" : "value 2"}');

        $this->assertTrue($parser->isComplete());
        $this->assertEquals(["key" => "value", "key 2" => "value 2"], $listener->get());
    }

    public function testIncompleteJSON_IsNotComplete() {
        $listener = new JSONToArrayListener();
        $parser = new JSONParser($listener);

        $parser->parse('[ { "id" : 1 }, { "id" : 2 }, { "id" : 3 }');

        $this->assertFalse($parser->isComplete());
    }

    public function testNestedObject() {
        $listener = new JSONToArrayListener();
        $parser = new JSONParser($listener);

        $parser->parse('[ { "id" : 1, "rights" : null, "valid" : false }, { "id" : 2, "rights" : null, "valid" : true }, { "id" : 3, "rights" : null, "valid" : false } ]');

        $this->assertTrue($parser->isComplete());
        $this->assertEquals([
            [ "id" => 1, "rights" => null, "valid" => false ],
            [ "id" => 2, "rights" => null, "valid" => true ],
            [ "id" => 3, "rights" => null, "valid" => false ],
        ], $listener->get());
    }
}
