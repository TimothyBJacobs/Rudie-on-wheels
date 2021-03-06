<?php

namespace app\forms;

use row\core\Options;
use row\utils\Email;
use app\models;
use app\specs\Output;

class RequestAccount extends \app\specs\SimpleForm {

	protected function elements( $defaults ) {
		$this->options->oninput = 'this.op.value=this.username.value;';
		return array(
			array(
				'type' => 'markup',
				'inside' => function( $form ) {
					return 'Username: <output name="op" xoninput="alert(this);this.value=this.form.username.value;">...</output>';
				},
			),
			'username' => array(
				'type' => 'text',
				'required' => true,
				'minlength' => 2,
				'validation' => function( $form ) {
					$db = $form->application->db;
					$usernameExists = models\User::count(array('username' => $form->input('username')));
					return $usernameExists ? 'This username is taken' : true;
				},
				'description' => Output::translate('Have you read our %1?', array(Output::ajaxLink(Output::translate('username guidelines', null, array('ucfirst' => false)), 'blog/page/username')))
			),
/*			'username_disclaimer' => array(
				'type' => 'markup',
				'text' => Output::translate('Have you read our %1?', array(Output::link(Output::translate('username guidelines', null, array('ucfirst' => false)), 'blog/user/guidelines/username')))
			),*/
			'password' => array(
				'type' => 'password',
				'required' => true,
				'minlength' => 0,
			),
			'color' => array(
				'title' => 'Favourite colour',
				'type' => 'colour',
				'required' => true,
				'minlength' => 2,
				'default' => 'white',
			),
			'category' => array(
				'title' => 'Favourite blog category',
				'type' => 'options',
				'options' => \app\models\Category::all('category_name <> ?', ''),
				'dummy' => '-- I have not the favourite',
				'validation' => function( $form, $name ) {
					if ( '' == $form->input($name, '') ) {
						// Can be empty
						$form->output($name, null);
						return true;
					}
					return $form->validateOptions($form, $name);
				},
			),
			'birthdate' => array(
				'title' => 'When\'s your dob?',
				'type' => 'date',
				'required' => true,
				'validation' => 'date',
				'default' => 'YYYY-MM-DD',
			),
			'bio' => array(
				'type' => 'textarea',
				'required' => true,
				'minlength' => 0,
				'rows' => 5,
				'regex' => '.*\w\s\w.*',
			),
			array( // markup elements don't need a (string) name
				'type' => 'markup',
				'outside' => '<fieldset><legend>'.Output::translate('Options').'</legend>',
			),
			'stupid' => array(
				'type' => 'checkbox',
				'required' => true,
				'name' => 'options[stupid]',
			),
			'this' => array(
				'type' => 'checkbox',
				'name' => 'options[this]',
			),
			'that' => array(
				'type' => 'checkbox',
				'name' => 'options[that]',
			),
			array( // markup elements don't need a (string) name
				'type' => 'markup',
				'outside' => '</fieldset>',
			),
			'email' => array(
				'type' => 'email',
				'required' => true,
				'validation' => 'email', // auto
				'description' => Output::translate('Only used for account activation. <strong>We won\'t store this.</strong>'),
			),
			'gender' => array(
				'type' => 'radio',
				'required' => true,
				'options' => array(
					'm' => Output::translate('Male'),
					'f' => Output::translate('Female'),
				),
			),
			'hobbies' => array(
				'type' => 'checkboxes',
				'options' => models\Category::all(),
				'required' => true,
				'minlength' => 2,
				'name' => 'misc[hobbies][]',
			),
			array(
				'type' => 'markup',
				'outside' => '<fieldset><legend>Terms</legend>',
			),
			'terms' => array(
				'type' => 'checkbox',
				'title' => Output::translate('I very much do agree on the terms yes yes'),
				'description' => 'Do you really? Huh? <b>Well?? Do ya??</b>',
				'required' => true,
			),
			array(
				'type' => 'markup',
				'outside' => '</fieldset>',
			),

			// only executed if ALL "required" validations pass
			array(
				'validation' => function( $form ) {
					return strlen($form->input('username')) <= strlen($form->input('password'));
				},
				'fields' => 'password',
				'message' => 'Your password must be at least as long as your username'
			),

			// only executed if [username] passes all its validations
			array(
				'require' => 'username',
				'validation' => function( $form ) {
					$usr = strtolower($form->input('username'));
					$clr = strtolower($form->input('color'));
					return !is_int(strpos($usr, $clr)) && !is_int(strpos($clr, $usr));
				},
				'fields' => array('username', 'color'),
				'message' => 'Username cannot contain Colour and vice versa'
			),
		);
	}


}


