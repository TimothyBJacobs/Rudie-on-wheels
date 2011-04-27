<?php

namespace app\controllers;

use Zend_Component_Session;
use row\utils\Image;

class fallbax extends \app\specs\Controller {

	public function allJS() {
		// create 1 JS file from several
		$files = array('framework', 'library-2', 'library-3');
		$js = '';
		foreach ( $files AS $file ) {
			$file = ROW_APP_WEB.'/js/'.$file.'.js';
			$js .= trim(file_get_contents($file)).";\n\n";
		}
//		file_put_contents(ROW_APP_WEB.'/js/all.js', $js);
		header('Content-type: text/javascript');
		echo "/* PHP generated JS */\n\n".$js;
	}

	public function image() {
//echo '<pre>';
		$image = new Image(ROW_VENDOR_ROW_PATH.'/drupal/imagecache/sample.png');
//print_r($image);
		$image->resize(0, 100);
//print_r($image);
		$image->output();
	}

	public function form( $form ) {
		$class = 'app\\forms\\'.$form;
		$form = new $class($this);
		$content = '';
		if ( $this->_post() ) {
			var_dump($form->validate($_POST));
			$content .= '<pre>'.print_r($form->errors(), 1).'</pre>';
		}
		$content .= $form->render();
		$content .= '<pre>$_POST: '.print_r($_POST, 1).'</pre>';
		$content .= '<pre>$form->output: '.print_r($form->output, 1).'</pre>';
		return $this->tpl->display(false, array('content' => $content));
	}

	function zend() {
		echo "<pre>\n";
		echo "doing Zend shizzle here...\n\n";

		$zendSession = new Zend_Component_Session;
		var_dump($zendSession);

		$zendUser = $zendSession->user();
		var_dump($zendUser);

		$zendACL = $zendSession->acl();
		var_dump($zendACL);
	}

	public function blog() {
		echo '<p>You are here because you are on an INVALID BLOG URI...</p>';
	}

	public function more( $path = '?' ) {
		echo '<p>More what?</p>';
		echo '<pre>';
		var_dump($path);
		echo '</pre>';
	}

	public function flush_apc_cache() {
		return $this->flush();
	}

	public function flush() {
		echo '<p>This is in the fallback module. Kewl =)</p>';

		echo '<pre>';
		var_dump(__METHOD__);
		echo '</pre>';
		echo '<pre>';
		print_r(func_get_args());
		echo '</pre>';

		var_dump($this->_dispatcher->cacheClear());
		var_dump(\Vendors::cacheClear());
		echo '<p>Also, I flushed the Vendors cache and Dispatch cache! You\'re welcome!</p>';
	}

	public function cache() {
		echo '<pre>';
		\Vendors::cacheLoad();
		print_r(\Vendors::$cache);

		$this->_dispatcher->cacheLoad();
		print_r($this->_dispatcher->cache);
		echo '</pre>';
	}

}


