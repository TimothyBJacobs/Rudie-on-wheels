<?php

namespace app\controllers;

use app\specs\Controller;
use app\models\User;

	class TestObject {
		function __construct() {}
	}

class dbController extends Controller {

	protected $config = array(
		'oele' => 'boele',
	);

	protected $_actions = array(
		'/' => 'index',
		'/index' => 'index',
		'/in' => 'in',
		'/replace' => 'replace',
		'/conditions' => 'conditions',
		'/build' => 'build',
		'/difference' => 'difference',
	);

	protected function _pre_action() {
		echo '<pre>'."\n";
	}


	protected function difference() {
		$schema = include(ROW_APP_PATH.'/config/db-schema.php');

		$dbTables = $this->db->_getTables();
		$schemaTables = array_keys($schema['tables']);

		$dropTables = array_diff($dbTables, $schemaTables);
		foreach ( $dropTables AS $table ) {
			echo "DROP TABLE ".$table.";\n";
		}
#echo "drop tables: ".implode(', ', $dropTables)."\n";

		$createTables = array_diff($schemaTables, $dbTables);
		foreach ( $createTables AS $table ) {
			echo "CREATE TABLE ".$table." ( ... );\n";
		}
#echo "create tables: ".implode(', ', $createTables)."\n";

		$compareTables = array_intersect($dbTables, $schemaTables);
		foreach ( $compareTables AS $table ) {
			$dbTable = $this->db->_getTableColumns($table);
			$dbColumns = array_keys($dbTable);

			$schemaTable = $schema['tables'][$table]['columns'];
			$schemaColumns = array_keys($schemaTable);

			$dropColumns = array_diff($dbColumns, $schemaColumns);
			foreach ( $dropColumns AS $column ) {
				echo "ALTER TABLE ".$table." DROP COLUMN ".$column.";\n";
			}
#echo "\ndrop columns: ".implode(', ', $dropColumns)."\n";
			$createColumns = array_diff($schemaColumns, $dbColumns);
			foreach ( $createColumns AS $column ) {
				echo "ALTER TABLE ".$table." ADD COLUMN ".$column." ...;\n";
			}
#echo "create columns: ".implode(', ', $createColumns)."\n";
		}

	}


	public function build() {
		$schema = include(ROW_APP_PATH.'/config/db-schema.php');

		foreach ( $schema['tables'] AS $tableName => $table ) {
			$table = options($table);
			echo "CREATE TABLE ".$tableName." (\n";

			$tableCharset = $table->get('charset', 'utf8');

			$primaries = array();
			foreach ( $table->columns AS $columnName => $column ) {
				$column = options($column);
				if ( $column->primary ) {
					$primaries[] = $columnName;
				}
			}
			$primaryIsAutoIncremenent = 1 == count($primaries);

			// columns
			$first = true;
			foreach ( $table->columns AS $columnName => $column ) {
				$column = options($column);
				$column->type = strtolower($column->type);

				$sqlColumnName = $this->db->escapeAndQuoteColumn($columnName);

				if ( 'boolean' == $column->type ) {
					$column->type = 'tinyint';
					$column->size = 1;
					if ( $column->_exists('default') ) {
						$column->default = (int)$column->default;
					}
				}

				$type = 'text' == $column->type && $column->size ? 'VARCHAR' : strtoupper($column->type);
				$type == 'INT' && $type = 'INTEGER';
				$size = $column->size ? "(".$column->size.")" : '';
				in_array($column->type, array('enum', 'set')) && $size = '('.implode(', ', array_map(array($this->db, 'escapeAndQuoteValue'), (array)$column->options)).')';
				$notnull = !$column->primary && !$column->get('null', true) ? ' NOT NULL' : '';
				$unsigned = $column->get('unsigned', false) ? ' CHECK( '.$sqlColumnName.' > 0 )' : '';
				$autoincrement = $primaryIsAutoIncremenent && $column->primary && $column->get('autoincrement', true) ? ' AUTOINCREMENT' : '';
				$primary = $column->primary && $primaryIsAutoIncremenent ? ' PRIMARY KEY' : '';
				$default = !$column->primary && false !== $column->get('default', false) ? ' DEFAULT '.( is_int($column->default) ? $column->default : "'".$column->default."'" ) : '';

				$columnCharset = $column->get('charset', $tableCharset);
				$charset = 'text' == $column->type ? ' COLLATE '.$columnCharset : '';

				$comma = $first ? ' ' : ',';
				echo "  ".$comma.$sqlColumnName." ".$type.$size.$unsigned.$primary.$charset.$notnull.$default.$autoincrement."\n";

				$first = false;
			}

			if ( !$table->_exists('indexes') ) {
				$table->indexes = array();
			}

			if ( $primaries && !$primaryIsAutoIncremenent ) {
				array_unshift($table->indexes, array('columns' => $primaries, 'primary' => true));
			}

			// PRIMARY KEY
			foreach ( $table->indexes AS $index ) {
				$index = options($index);
				if ( $index->primary ) {
					echo "  ,PRIMARY KEY (".implode(', ', array_map(array($this->db, 'escapeAndQuoteColumn'), $index->columns)).")\n";
				}
			}

			echo ");\n\n";

			// indexes
			foreach ( $table->indexes AS $index ) {
				$index = options($index);
				if ( !$index->primary ) {
					$unique = $index->unique ? ' UNIQUE' : '';

					echo "CREATE".$unique." INDEX i".rand(0, 999999)." ON ".$tableName." (".implode(', ', array_map(array($this->db, 'escapeAndQuoteColumn'), $index->columns)).");\n\n";
				}
			}
		}

		print_r($schema);
	}


	public function conditions() {
		$this->db->update('oele', array(
			'a' => 'a+1',
			'b = b+1',
		), array(
			'x' => 'X',
			'y > 4'
		));
	}


	public function replace() {
		$this->db->fetch('SELECT 1 FROM oele WHERE (boele IN (?) OR tra = ?) AND bla >= ?', array(array(1,2,3,'x'), 'gister', 4, 19));
	}


	protected function debugQuery() {
		static $c = -1;
		if ( $c != count($this->db->queries) ) {
			echo "\n[ sql query: \"".end($this->db->queries)."\" ]\n";
			$c = count($this->db->queries);
		}
	}


	public function in() {
		$users = User::all('? AND user_id IN (?)', array(1, array(2, 3, 4)));
		var_dump(User::dbObject()->error());
		echo "\n".end($this->db->queries)."\n";
	}


	public function index() {

		$tables = $this->db->_getTables();
		$this->debugQuery();
		var_dump($tables);
		echo "\n";

		$result = $this->db->result('SELECT user_id, username, password, full_name, bio, access FROM users ORDER BY RAND() LIMIT 2');
		$this->debugQuery();
		var_dump($result);
		echo "\n";

		$objects = $result->allObjects('app\models\User'); // post_fill will NOT be executed
		$this->debugQuery();
		var_dump($objects);
		echo "\n";

		$count = $this->db->count('users', '1 ORDER BY RAND() LIMIT 2');
		$this->debugQuery();
		var_dump($count);
		echo "\n";

/*		$count = $this->db->countRows('SHOW TABLES');
		$this->debugQuery();
		var_dump($count);
		echo "\n";*/

		$objects = $this->db->fetch('SELECT * FROM users ORDER BY RAND() LIMIT 2', 'app\models\User'); // post_fill WILL be executed
		$this->debugQuery();
		var_dump($objects);
		echo "\n";

		$posts = $this->db->selectFields('users u', 'username, (SELECT COUNT(1) FROM posts p WHERE p.author_id = u.user_id)', '1 ORDER BY RAND()');
		$this->debugQuery();
		var_dump($posts);

		$usernames = $this->db->selectFieldsNumeric('users', 'username', '1 ORDER BY RAND()');
		$this->debugQuery();
		var_dump($usernames);

		$id = $this->db->selectOne('posts', 'MAX(post_id)', '1');
		$this->debugQuery();
		var_dump($id);

		$users = $this->db->selectByField('users', 'user_id', '1 ORDER BY RAND() LIMIT 4');
		$this->debugQuery();
		var_dump($users);

	}

}


