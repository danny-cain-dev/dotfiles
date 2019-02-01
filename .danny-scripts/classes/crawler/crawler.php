<?php

namespace Danny\Scripts\Crawler;

use Danny\Scripts\Crawler\Listener\CrawlerListener;
use Danny\Scripts\Crawler\Models\URL;

class Crawler {

	/**
	 * @var URL[]
	 */
	protected $queue = [];
	protected $crawled = [];

	/**
	 * @var \Danny\Scripts\Crawler\Models\URL
	 */
	protected $initial;
	/**
	 * @var \Danny\Scripts\Crawler\Listener\CrawlerListener
	 */
	protected $listener;

	/**
	 * Crawler constructor.
	 *
	 * @param \Danny\Scripts\Crawler\Listener\CrawlerListener $listener
	 */
	public function __construct( CrawlerListener $listener ) { $this->listener = $listener; }

	public function crawl(URL $url) {
		$this->initial = $url;
		$this->queue = [$url];
		$this->crawled = [];

		while(count($this->queue) > 0) {
			$this->crawlNext();
		}
	}

	protected function queue(URL $url) {
		$this->queue[] = $url;
	}

	protected function scanDOMTagsForResources(URL $url, \DOMNodeList $nodes, $attribute) {
		for ($i = 0; $i < $nodes->count(); $i ++) {
			$val = $nodes->item($i)->getAttribute($attribute);
			if ($val == '')
				continue;

			$newURL = URL::Parse($val, $url);
			if ($newURL === null) {
				continue;
			}

			$newURL->setFragment('');
			if ($newURL->getDomain() == $this->initial->getDomain()) {
				$this->listener->foundURL($url, $newURL, $nodes->item($i)->tagName);
				$this->queue($newURL);
			}
		}
	}

	public function scanHTML(URL $url, $html) {
		\libxml_use_internal_errors(true);
		$dom = new \DOMDocument();
		$dom->loadHTML($html);

		$this->scanDOMTagsForResources($url, $dom->getElementsByTagName("a"), "href");
		$this->scanDOMTagsForResources($url, $dom->getElementsByTagName("link"), "href");
		$this->scanDOMTagsForResources($url, $dom->getElementsByTagName("img"), "src");
		$this->scanDOMTagsForResources($url, $dom->getElementsByTagName("video"), "src");
		$this->scanDOMTagsForResources($url, $dom->getElementsByTagName("script"), "src");
		$this->scanDOMTagsForResources($url, $dom->getElementsByTagName("form"), "action");
	}

	public function crawlNext() {
		$url = array_shift($this->queue);
		if (\in_array((string)$url, $this->crawled)) {
			// already crawled
			return;
		}

		if (!$this->listener->scan($url)) {
			return;
		}
		$this->crawled[] = (string)$url;

		$headers = [];

		$curl = curl_init((string)$url);
		curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, \CURLOPT_HEADERFUNCTION, function($curl, $header) use(&$headers) {
			$pos = strpos($header, ':');
			if ($pos === false)
				return strlen($header);

			$key = trim(substr($header, 0, $pos));
			$value = trim(substr($header, $pos + 1));
			if (!isset($headers[$key]))
				$headers[$key] = [];
			$headers[$key][] = $value;
			return strlen($header);
		});

		if (!isset($headers['Content-Type']))
			$headers['Content-Type'] = ["text/html"];

		$ct = $headers['Content-Type'];
		$ct = $ct[count($ct) - 1];
		$pos = strpos($ct, ';');
		if ($pos !== false)
			$ct = substr($ct, 0, $pos);

		$response = curl_exec($curl);
		$info = \curl_getinfo($curl);

		switch(floor($info['http_code'] / 100)) {
			case 2:
				// 2xx - Ok
				break;
			case 3:
				$redirect = URL::Parse($info['redirect_url']);
				if ($redirect === null) {
					\var_dump($info);exit;
				}

				$redirect->setFragment('');
				$this->listener->foundRedirect($url, $redirect);
				$this->queue($redirect);
				return;
			default:
				$this->listener->failedToRetrieve($url, $info['http_code']);
				return;
		}

		switch($ct) {
			case 'text/html':
				$this->scanHTML($url, $response);
				break;
			default:
				// non html - nothing to do
		}
	}
}