<?php

namespace Danny\Scripts\DataTransfer;

use Danny\Scripts\Parsers\JSON\JSONParser;
use Danny\Scripts\Parsers\JSON\Listeners\JSONExtractedObjectsListener;
use DPress\Abstraction\DataLayer\Databases\Database;
use DPress\Abstraction\DataLayer\DataLayer;
use DPress\Abstraction\DataLayer\DataMapping\DataMapper;
use DPress\Abstraction\DataLayer\Schema\Models\FieldModel;
use DPress\Abstraction\DataLayer\Schema\Models\TableModel;
use DPress\Abstraction\DataLayer\Schema\SchemaManager;
use DPress\Abstraction\DataLayer\SQL\SQLBuilder;

class Importer {

	/**
	 * @var  Database
	 */
	protected $database;

	/**
	 * @var resource
	 */
	protected $source;

	/**
	 * Exporter constructor.
	 *
	 * @param Database $database
	 * @param resource $source
	 */
	public function __construct( Database $database, $source ) {
		$this->database = $database;
		$this->source   = $source;
	}

	public function import() {
		$schema = new SchemaManager();
		$sql = new SQLBuilder();
		$datalayer = new DataLayer($this->database, $sql, new DataMapper($this->database, $sql, $schema), $schema);

		$listener = new JSONExtractedObjectsListener([
			["schema", "*"],
			["data", "*", "[]"]
		], function($matchedPath, $actualPath, $match) use($datalayer) {
			switch($actualPath[0]) {
				case 'schema':
					$fields = array();
					foreach($match['_fields'] as $fieldData) {
						$fields[] = new FieldModel($fieldData['_fieldName'], $fieldData['_dataType'], $fieldData['_fieldLength'], $fieldData['_autoIncrement']);
					}
					$table = new TableModel($match['_tableName'], $fields, $match['_primaryKeys'] ?? array(), $match['_foreignKeys'] ?? array());
					$datalayer->schema()->addTable($table);
					$datalayer->applySchemaDiff($datalayer->generateSchemaDiff());
					break;
				case 'data':
					$statement = $datalayer->sql()->insert()->into($actualPath[1]);
					foreach($match as $field => $val) {
						$statement->set($field, ':'.$field);
						$statement->bindValue($field, $val);
					}
					$datalayer->database()->statement($statement);
					break;
			}
		});

		$this->database->raw_statement("SET FOREIGN_KEY_CHECKS=0", array());
		$parser = new JSONParser($listener);
		while(!feof($this->source)) {
			$parser->parse(\fread($this->source, 1024));
		}
		$listener->finish();
		$this->database->raw_statement("SET FOREIGN_KEY_CHECKS=1", array());
	}
}