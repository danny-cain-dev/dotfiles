<?php

namespace Danny\Scripts\Crawler\Listener;

use Danny\Scripts\Crawler\Models\URL;

interface CrawlerListener {
	function foundURL(URL $sourcePage, URL $url, $tagName = '');
	function failedToRetrieve(URL $url, $response_code);
	function foundRedirect(URL $sourcePage, URL $url);
	function scan(URL $url);
}