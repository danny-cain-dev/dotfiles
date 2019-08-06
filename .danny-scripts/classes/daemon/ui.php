<?php

namespace Danny\Scripts\Daemon;

class UI {
	private $status = '';
	private $currentLine = '';

	public function bind() {
		// bind up arrow to magic
		// see http://ascii-table.com/ansi-escape-sequences.php
//		echo "\e[(224;72);hello;p";
	}

	private function clearStatus() {
		echo "\r\e[K";
		if ($this->currentLine) {
			echo "\e[1A";
			echo "\r".$this->currentLine;
		}
	}

	/**
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus($status) {
		$this->clearStatus();
		$this->status = $status;
		if ($this->currentLine)
			echo "\n";

		echo $this->status;
	}

	public function writeLine($text) {
		$this->clearStatus();

		$this->currentLine = '';
		echo $text."\n";
		echo $this->status;
	}

	public function write($text) {
		$this->clearStatus();
		$this->currentLine .= $text;
		echo $text."\n";
		echo $this->status;
	}
}