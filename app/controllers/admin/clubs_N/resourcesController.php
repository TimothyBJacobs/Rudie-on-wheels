<?php

namespace app\controllers\admin\clubs_N;

use app\specs\Controller;

class resourcesController extends Controller {

	public function index() {
		echo '<pre>Home of '.__METHOD__."\n";
		print_r($this->_arguments);
	}

}


