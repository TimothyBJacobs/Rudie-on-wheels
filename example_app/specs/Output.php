<?php

namespace app\specs;

/**
 * It's very likely that some kind of Output function is missing
 * in the very basic row\Output. If there is: extend it and use
 * that one instead.
 * 
 * I added markdown() because my app likes Markdown and
 * ajaxLink() because my app uses a lot of ajax overlays (but not
 * really). (Obviously you need to create the javascript function
 * Element.openInAjaxOverlay yourself.)
 * 
 * The Output class isn't just used for static Output renderers.
 * It's also the view class, so you could do some configuration
 * in the ->_init() function, or change the way calls to ->display()
 * are handled. Etc etc etc.
 * 
 * Don't forget to use app\specs\Output instead of row\Output!
 */

class Output extends \row\Output {

	protected function _init() {
		parent::_init();

		$this->assign('Application', $this::$application);
		$this->assign('User', $this::$application->user);
	}

	static public function ajaxlink( $text, $path, $options = array() ) {
		$options['onclick'] = 'return openInAjaxPopup(this.href);';
		return static::link($text, $path, $options);
	}

	static public function ajaxActionlink( $text, $path, $options = array() ) {
		if ( isset($options['action']) ) {
			$options['onclick'] = 'return doAjaxAction(this, '.$options['action'].');';
			unset($options['action']);
		}
		return static::link($text, $path, $options);
	}

}

Output::$class = 'app\specs\Output';


