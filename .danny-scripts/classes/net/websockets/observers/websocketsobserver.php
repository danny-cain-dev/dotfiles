<?php

namespace DannyCain\Net\WebSockets\Observers;

use DannyCain\Net\WebSockets\Models\WebSocketMessageModel;

interface WebsocketsObserver {
	public function send(WebSocketMessageModel $message);
	public function receive(WebSocketMessageModel $message);
}