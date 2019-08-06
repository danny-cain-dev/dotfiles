<?php

namespace Danny\Scripts\Daemon;

class TaskManager {
	private $tasks = [];
	private $running = false;
	private $onComplete = null;

	public function onComplete(\Closure $callback) {
		$this->onComplete = $callback;
	}

	public function countRunningTasks() {
		return count($this->tasks);
	}

	public function runTask($name, $task) {
		if (!($task instanceof \Generator)) {
			$result = $task();
			if ($result && $result instanceof \Generator)
				$task = $result;
		}

		$this->tasks[$name] = $task;
	}

	private function taskComplete($name) {
		$task = $this->tasks[$name];
		unset($this->tasks[$name]);

		if ($this->onComplete)
			call_user_func($this->onComplete, $name, $task);
	}

	private function tick($name) {
		$task = $this->tasks[$name];

		if ($task instanceof \Generator) {
			$task->next();
			if (!$task->valid())
				$this->taskComplete($name);
		} elseif ($task instanceof \Closure) {
			$complete = false;
			$task($complete);
			if ($complete) {
				$this->taskComplete($name);
			}
		}

	}

	public function run() {
		$this->running = true;
		while($this->running) {
			foreach(array_keys($this->tasks) as $name) {
				usleep(50);
				$this->tick($name);
			}
		}
	}
}