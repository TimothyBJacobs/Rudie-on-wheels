<?php

namespace row\database;

use row\core\Object;
use row\database\Adapter; // interface
use row\database\ModelException; // model errors

class Model extends Object {

	public function __tostring() {
		return get_class($this).' model';
	}

	static public $_db;

	/**
	 * 
	 */
	static public function dbObject( Adapter $db = null ) {
		if ( $db ) {
//			if !is_a($db, 'Adapter') ) {
//				throw new ModelException('Database object IS NOT an instance of interface Adapter.');
//			}
			self::$_db = $db;
		}
		return self::$_db;
	}


	static public $_table = '';

	static public $_pk = array('id');


	const GETTER_ONE		= 1;
	const GETTER_ALL		= 2;
	const GETTER_FUNCTION	= 3;
	const GETTER_FIRST		= 4;

	static public $_getters = array(
//		'author' => array( self::GETTER_ONE, true, 'User', 'author_id', 'user_id' ).
//		'comments' => array( self::GETTER_ALL, true, 'Comment', 'post_id', 'post_id' ).
//		'first_comment' => array( self::GETTER_FIRST, true, 'Comment', 'post_id', 'post_id' ).
//		'followers' => array( self::GETTER_FUNCTION, true, 'getFollowerUserObjects' ).
	);


	/**
	 * Enables calling of Post::update with defined function _update
	 */
	static public function __callStatic( $func, $args ) {
//		static $n = 0;
//		$n++;
		if ( '_' != $func{0} ) {
			$func = '_'.$func;
		}
		if ( !method_exists(get_called_class(), $func) ) {
			throw new \row\database\ModelException('Methodo "'.$func.'" no existo!');
		}
//var_dump($func);
//if ( 10 <= $n ) exit;
		return call_user_func_array(array('static', $func), $args);
	} // END __callStatic() */


	/**
	 * 
	 */
	public static function _query( $conditions ) {
		return 'SELECT * FROM '.static::$_table.' WHERE '.$conditions;
	}


	/**
	 * 
	 */
	static public function _byQuery( $query ) {
		$class = get_called_class();
		if ( class_exists($class.'Record') /*&& is_a($class.'Record', get_called_class())*/ ) { // Is the AND .. overkill? Or necessary?
			$class = $class.'Record';
		}
		return static::dbObject()->fetch($query, $class);
	}

	/**
	 * 
	 */
	static public function _fetch( $conditions ) {
		$query = static::_query($conditions);
		return static::_byQuery($query);
	}

	/**
	 * 
	 */
	static public function _all( $conditions, $params = array() ) {
		return static::_fetch($conditions);
	}

	/**
	 * Returns exactly one object with the matching conditions OR throws a model exception
	 */
	static public function _one( $conditions, $params = array() ) {
		$conditions = static::dbObject()->stringifyConditions($conditions);
		$conditions = static::dbObject()->addLimit($conditions, 2);
		$r = static::_fetch($conditions);
		if ( !isset($r[0]) || isset($r[1]) ) {
			throw new ModelException('Not exactly one record returned.');
		}
		return $r[0];
	}

	/**
	 * Returns null or the first object with the matching conditions
	 */
	static public function _first( $conditions, $params = array() ) {
		$conditions = static::dbObject()->stringifyConditions($conditions);
		$conditions = static::dbObject()->addLimit($conditions, 1);
		$r = static::_fetch($conditions);
		if ( isset($r[0]) ) {
			return $r[0];
		}
	}

	/**
	 * 
	 */
	static public function _get( $pkValues, $moreConditions = array(), $params = array() ) {
		$pkValues = (array)$pkValues;
		$pkColumns = (array)static::$_pk;
		if ( count($pkValues) !== count($pkColumns) ) {
			throw new ModelException('Invalid number of PK arguments ('.count($pkValues).' instead of '.count($pkColumns).').');
		}
		$pkValues = array_combine($pkColumns, $pkValues);
		$conditions = static::dbObject()->stringifyConditions($pkValues, 'AND', static::$_table);
		if ( $moreConditions ) {
			$conditions .= ' AND '.static::dbObject()->stringifyConditions($moreConditions);
		}
		return static::_one($conditions);
	}

	/**
	 * 
	 */
	static public function _delete( $conditions, $params = array() ) {
		return static::dbObject()->delete(static::$_table, $conditions);
	}

	/**
	 * 
	 */
	static public function _update( $updates, $conditions, $params = array() ) {
//print_r(func_get_args());
		return static::dbObject()->update(static::$_table, $updates, $conditions, $params);
	}

	/**
	 * 
	 */
	static public function _insert( $values ) {
		return static::dbObject()->insert(static::$_table, $values);
	}

	/**
	 * 
	 */
	static public function _replace( $values, $conditions ) {
		return static::dbObject()->replace(static::$_table, $values, $conditions);
	}


	/**
	 * 
	 */
	public function __construct( $data = null ) {
		if ( null !== $data ) {
			$this->_fill( $data );
		}
		$this->_fire('init', array($data));
	}

	/**
	 * Dummy init function to enable executing init without checking callability
	 */
	public function _init( $withData = true ) {}

	/**
	 * 
	 */
	public function _fill( $data = null ) {
		foreach ( (array)$data AS $k => $v ) {
			$this->$k = $v;
		}
	}


	/**
	 * Returns an associative array of PK keys + values
	 */
	public function _pkValue( $strict = true ) {
		return $this->_values((array)static::$_pk, $strict);
	}

	/**
	 * Returns an associative array of keys + values
	 */
	public function _values( Array $columns, $strict = false ) {
		$values = array();
		foreach ( (array)$columns AS $field ) {
			if ( $this->_exists($field) ) {
				$values[$field] = $this->$field;
			}
			else if ( $strict ) {
				return false;
			}
		}
		return $values;
	}


	/**
	 * 
	 */
	protected function __getter( $key ) {
		$getter = $this::$_getters[$key];
		$type = $getter[0];
		$cache = $getter[1];
		$class = $function = $getter[2];
		switch ( $type ) {
			case self::GETTER_ONE:
			case self::GETTER_ALL:
			case self::GETTER_FIRST:
				$localColumns = (array)$getter[3];
				$localValues = $this->_values($localColumns);
//print_r($localValues);
//				$localValues = array_values($localValues); // is this necessary?

				$foreignTable = $class::$_table; // does this work? $class might (syntactically) as well be an object.
				$foreignColumns = (array)$getter[4];
//				$foreignColumn = static::$_db->aliasPrefix($foreignTable, $foreignColumn);
//				$foreignClause = $foreignColumn . ' = ' . ;

				$conditions = array_combine($foreignColumns, $localValues);
//print_r($conditions);
				$conditions = static::dbObject()->stringifyConditions($conditions, 'AND', $foreignTable);
//var_dump($conditions);
				$retrievalMethods = array(
					self::GETTER_ONE => '_one',
					self::GETTER_ALL => '_all',
					self::GETTER_FIRST => '_first',
				);
				$retrievalMethod = $retrievalMethods[$type];
//var_dump($retrievalMethod);
				$r = call_user_func(array($class, $retrievalMethod), $conditions);
//var_dump($r);
				if ( $cache ) {
					$this->$key = $r;
				}
				return $r;
			break;
			case self::GETTER_FUNCTION:
				$r = $this->$function();
//var_dump($r);
				if ( $cache ) {
					$this->$key = $r;
				}
				return $r;
			break;
		}
		// if you're here, you cheat
	}

	/**
	 * 
	 */
	public function __get( $key ) {
		if ( isset($this::$_getters[$key]) ) {
			return $this->__getter($key);
		}
		else if ( $this->_exists($key) ) {
			return $this->$key;
		}
	}


	/**
	 * 
	 */
	public function update( $updates ) {
		if ( !is_scalar($updates) ) {
			$this->_fill((array)$updates);
		}
		$conditions = $this->_pkValue(true);
//print_r($conditions); exit;
		return $this::_update($updates, $conditions);
	}

	/**
	 * 
	 */
	public function delete() {
		return $this::_delete($this->_pkValue(true));
	}


} // END Class Model

