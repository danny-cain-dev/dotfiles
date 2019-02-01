<?php

namespace Danny\Scripts\Parsers\JSON\Listeners;

use Danny\Scripts\Parsers\JSON\Models\JSONParserState;

interface JSONParserListener {
	function value(JSONParserState $state);
}