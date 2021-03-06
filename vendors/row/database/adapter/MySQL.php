<?php

namespace row\database\adapter;

use row\database\Adapter;
use row\database\adapter\MySQLi;
use row\database\DatabaseException;

class MySQL extends Adapter {

	/* Reflection */
	public function _getTables() {
		$tables = $this->fetch('SHOW TABLES');
		$tables = array_map(function($r) {
			return reset($r);
		}, $tables);
		usort($tables, 'strcasecmp');
		return $tables;
	}

	public function _getTableColumns( $table ) {
		$_columns = $this->fetch('EXPLAIN '.$table);
		$columns = array();
		foreach ( $_columns AS $c ) {
			$c2 = array(
				'type' => strtolower(is_int($p=strpos($c['Type'], '(')) ? substr($c['Type'], 0, $p) : $c['Type']),
				'null' => 'NO' !== $c['Null'],
				'default' => $c['Default'],
				'primary' => 'PRI' === $c['Key'],
			);

			$columns[$c['Field']] = $c2;
		}
		return $columns;
	}

	public function _getPKColumns( $table ) {
		$columns = $this->_getTableColumns($table);
		$columns = array_filter($columns, function($c) {
			return $c['primary'];
		});
		$columns = array_keys($columns);
		return $columns;
	}


	static public function initializable() {
		return function_exists('mysql_connect');
	}

	static public function open( $info, $do = true ) {
		if ( MySQLi::initializable() ) {
			return new MySQLi($info, $do);
		}
		return new self($info, $do);
	}

	public function connect() {
		$connection = $this->connectionArgs;
		$this->db = @mysql_connect($connection->host, $connection->user ?: 'root', $connection->pass ?: '');
		if ( !$this->db || ( $connection->dbname && !@mysql_select_db($connection->dbname, $this->db) ) ) {
			return false;
		}
		$this->_fire('post_connect');
	}

	public function connected() {
		if ( is_bool($this->_connected) ) {
			return $this->_connected;
		}

		if ( !is_resource($this->db) ) {
			return $this->_connected = false;
		}

		try {
			$r = $this->query('SHOW TABLES');
			return $this->_connected = (false !== $r);
		}
		catch ( DatabaseException $ex ) {}

		return $this->_connected = false;
	}

	public function _post_connect() {
		if ( $this->connected() ) {
			if ( $this->connectionArgs->names ) {
				$this->execute('SET NAMES \''.$this->connectionArgs->names.'\'');
			}
			else if ( $this->connectionArgs->charachter_set ) {
				$this->execute('SET CHARACTER SET \''.$this->connectionArgs->charachter_set.'\'');
			}
		}
	}

	public function query( $query ) {
		$this->queries[] = $query;

		$q = mysql_query($query, $this->db);
		if ( !$q ) {
			return $this->except($query.' -> '.$this->error());
		}

		return $q;
	}

	public function execute( $query ) {
		return $this->query($query);
	}

	public function error() {
		return mysql_error($this->db);
	}

	public function errno() {
		return mysql_errno($this->db);
	}

	public function affectedRows() {
		return mysql_affected_rows($this->db);
	}

	public function insertId() {
		return mysql_insert_id($this->db);
	}

	public function escapeValue( $value ) {
		return mysql_real_escape_string((string)$value, $this->db);
	}

#	public function quoteColumn( $column ) {
#		return !is_int(strpos($column, ' ')) && !is_int(strpos($column, '.')) ? '`'.$column.'`' : $column;
#	}

#	public function quoteTable( $table ) {
#		return !is_int(strpos($table, ' ')) && !is_int(strpos($table, '.')) ? '`'.$table.'`' : $table;
#	}

}



class MySQLResult extends \row\database\QueryResult {

	public function singleResult() {
		return mysql_result($this->result, 0);
	}

	public function nextObject( $class = '\stdClass', $args = array() ) {
		return mysql_fetch_object($this->result, $class, $args);
	}

	public function nextAssocArray() {
		return mysql_fetch_assoc($this->result);
	}

	public function nextNumericArray() {
		return mysql_fetch_row($this->result);
	}

}


