<?php

use Danny\Scripts\Crawler\Models\URL;
use PHPUnit\Framework\TestCase;

class URLTest extends TestCase {
	public function testParseURL() {
		$input = 'http://www.dannycain.com/';
		$output = URL::Parse($input);

		$this->assertEquals($input, (string)$output);
	}

	public function testParseURI() {
		$input = '/test?query=query_string';
		$url = URL::Parse($input, URL::Parse('http://www.dannycain.com/testing/test'));
		$this->assertEquals('http://www.dannycain.com/test?query=query_string', (string)$url);
	}

	public function testMailto() {
		$input = 'mailto:danny@dannycain.com';
		$url = URL::Parse($input, URL::Parse('http://www.dannycain.com/testing/test'));
		$this->assertNull($url);
	}

	public function testRelativePaths() {
		$base = URL::Parse('http://www.dannycain.com/directory/');
		$relative = '../another_directory';
		$url = URL::Parse($relative, $base);
		$this->assertEquals('http://www.dannycain.com/another_directory', (string)$url);
	}
}