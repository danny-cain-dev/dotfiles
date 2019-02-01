<?php

namespace Danny\Scripts\DataTransfer;

use DPress\Abstraction\DataLayer\Databases\Database;
use DPress\Abstraction\DataLayer\Schema\Models\TableModel;

class Exporter {

	/**
	 * @var  Database
	 */
	protected $database;

	/**
	 * @var resource
	 */
	protected $output;

	protected $structure = true;
	protected $data = true;

	/**
	 * Exporter constructor.
	 *
	 * @param Database $database
	 * @param resource $output
	 */
	public function __construct( Database $database, $output ) {
		$this->database = $database;
		$this->output   = $output;
	}

	/**
	 * @param bool $structure
	 */
	public function setExportStructure( $structure ) {
		$this->structure = $structure;
	}

	/**
	 * @param bool $data
	 */
	public function setExportData( $data ) {
		$this->data = $data;
	}

	protected function toArray($object) {
		if (\is_scalar($object)) {
			return $object;
		}
		if (\is_resource($object)) {
			return null;
		}

		$ret = array();
		if (\is_array($object)) {
			foreach($object as $key => $val)
				$ret[$key] = $this->toArray($val);
			return $ret;
		}

		if (\is_object($object)) {
			$reflect = new \ReflectionObject($object);
			foreach($reflect->getProperties() as $prop) {
				$prop->setAccessible(true);
				$ret[$prop->getName()] = $this->toArray($prop->getValue($object));
			}

			return $ret;
		}
	}

	/**
	 * @param TableModel[] $schema
	 */
	protected function exportSchema($schema) {
		$first = true;
		foreach($schema as $table) {
			if (!$first)
				fwrite($this->output, ",");
			fwrite($this->output, '"'.$table->getTableName().'" :'."\n");
			fwrite($this->output, \json_encode($this->toArray($table)));
			fwrite($this->output, "\n");

			$first = false;
		}
	}

	/**
	 * @param TableModel $table
	 */
	protected function exportTableData($table) {
		$first = true;
		foreach($this->database->raw_query('SELECT * FROM `'.$table->getTableName().'`', array()) as $row){
			if (!$first)
				fwrite($this->output, ",\n");

			fwrite($this->output, \json_encode($row));
			$first = false;
		}
		fwrite($this->output, "\n");
	}

	/**
	 * @param TableModel[] $schema
	 */
	protected function exportData($schema) {
		$first = true;

		foreach($schema as $table) {
			if (!$first)
				fwrite($this->output, ",");

			fwrite($this->output,\json_encode($table->getTableName())." : [\n");
			$this->exportTableData($table);
			fwrite($this->output, "]\n");

			$first = false;
		}
	}

	public function export() {
		$schema = $this->database->loadSchema();
		$started = microtime(true);

		fwrite($this->output, "{\n");
		fwrite($this->output, '"version" : "1.0",'."\n");
		fwrite($this->output, '"started" : "'.date('Y-m-d H:i:s', $started).'",'."\n");
		if ($this->structure) {
			fwrite($this->output, '"schema" : {'."\n");
			$this->exportSchema($schema);
			fwrite($this->output, "}");

			if ($this->data)
				fwrite($this->output, ",");
			fwrite($this->output, "\n");
		}

		if ($this->data) {
			fwrite($this->output, '"data" : {'."\n");
			$this->exportData($schema);
			fwrite($this->output, "}\n");
		}
		$finished = microtime(true);

		fwrite($this->output, ",\n");
		fwrite($this->output, '"finished" : "'.date('Y-m-d H:i:s', $finished).'",'."\n");
		fwrite($this->output, '"duration" : "'.($finished - $started).'"'."\n");

		fwrite($this->output, "}\n");
	}
}