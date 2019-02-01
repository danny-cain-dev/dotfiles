<?php

namespace Danny\Scripts\Crawler\Listener;

use Danny\Scripts\Crawler\Models\URL;

class TextLogCrawlerListener implements CrawlerListener {
	function foundURL( URL $sourcePage, URL $url, $tagName = '' ) {
		echo "Found ".(string)$url." on ".(string)$sourcePage."\n";
	}

	function failedToRetrieve( URL $url, $response_code ) {
		echo $response_code.": ".(string)$url."\n";
	}

	function foundRedirect( URL $sourcePage, URL $url ) {
		echo (string)$sourcePage." -> ".(string)$url."\n";
	}

	function scan( URL $url ) {
		return true;
	}
}