<?php

namespace row\applets\scaffolding;

use row\database\Model;
use row\Output;

class Controller extends \row\Controller {

	protected $_actions = array(
		'/'								=> 'tables',
		'/table-structure/*'			=> 'table_structure',
		'/table-data/%'					=> 'table_data',
		'/table-data/%/add'				=> 'add_data',
		'/table-data/%/add/save'		=> 'insert_data',
		'/table-data/%/delete'			=> 'delete_record',
		'/table-data/%/view'			=> 'table_record',
		'/table-data/%/save'			=> 'save_table_record',
	);

	protected function _init() {
		parent::_init();

		$this->view = new Output($this);
		$this->view->viewsFolder = __DIR__.'/views';
		$this->view->viewLayout = __DIR__.'/views/_layout';
		$this->view->assign('app', $this);
	}

	public function delete_record( $table ) {
		$pkValues = $_GET['pk'];
		$pkColumns = Model::dbObject()->_getPKColumns($table);
		if ( count($pkColumns) !== count($pkValues) ) {
			exit('Invalid PK');
		}

		$pkValues = array_combine($pkColumns, $pkValues);

		$db = Model::dbObject();
		if ( !$db->delete($table, $pkValues) ) {
			exit($db->error());
		}

		$this->_redirect($this->_url('table-data', $table));
	}

	public function save_table_record( $table, $pkValues ) {
		$pkColumns = Model::dbObject()->_getPKColumns($table);
		$pkValues = explode(',', $pkValues);
		if ( count($pkColumns) !== count($pkValues) ) {
			exit('Invalid PK');
		}
		$pkValues = array_combine($pkColumns, $pkValues);

		foreach ( $_POST['data'] AS $k => $v ) {
			if ( isset($_POST['null'][$k]) ) {
				$_POST['data'][$k] = null;
			}
		}

		$db = Model::dbObject();
		if ( !$db->update($table, $_POST['data'], $pkValues) ) {
			exit($db->error());
		}

		$this->_redirect($this->_url('table-data', $table));
	}

	public function insert_data( $table ) {
		foreach ( $_POST['data'] AS $k => $v ) {
			if ( isset($_POST['null'][$k]) ) {
				$_POST['data'][$k] = null;
			}
		}

		$db = Model::dbObject();
		if ( !$db->insert($table, $_POST['data']) ) {
			exit($db->error());
		}

		$this->_redirect($this->_url('table-data', $table));
	}

	public function add_data( $table ) {
		$columns = Model::dbObject()->_getTableColumns($table);
		$pkColumns = Model::dbObject()->_getPKColumns($table);
		return $this->view->display('add_data', get_defined_vars());
	}

	public function table_record( $table ) {
		$pkValues = $_GET['pk'];
		$pkColumns = Model::dbObject()->_getPKColumns($table);
		if ( count($pkColumns) !== count($pkValues) ) {
			exit('Invalid PK');
		}

		$pkValues = array_combine($pkColumns, $pkValues);
		$data = Model::dbObject()->select($table, $pkValues, array(), true);
		$columns = Model::dbObject()->_getTableColumns($table);
		return $this->view->display('table_record', get_defined_vars());
	}

	public function table_data( $table ) {
		$pkColumns = Model::dbObject()->_getPKColumns($table);
		$data = Model::dbObject()->select($table, '1');
		if ( !$data ) {
//			exit('no data');
		}
		return $this->view->display('table_data', get_defined_vars());
	}

	public function table_structure( $table ) {
		$columns = Model::dbObject()->_getTableColumns($table);
		return $this->view->display('table_structure', get_defined_vars());
	}

	public function tables() {
		$tables = Model::dbObject()->_getTables();
		return $this->view->display('tables', get_defined_vars());
	}

	public function _url( $action = '', $more = '', $query = array() ) {
		$x = explode('/', ltrim($this->dispatcher->requestPath, '/'));

		$query = $query + $_GET;
		$uri = Output::url($x[0].( $action ? '/'.$action.( $more ? '/'.$more : '' ) : '' ), array('get' => $query));

		return $uri;
	}

}


