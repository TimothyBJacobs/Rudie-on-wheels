<?phpnamespace app\forms;use row\core\Options;use row\utils\Email;use app\models;use app\specs\Output;class BRMember extends \app\specs\SimpleForm {	protected function elements( $defaults = null, $options = array() ) {		return array(			'username' => array(				'type' => 'text',				'required' => true,				'minlength' => 0,			),			'firstname' => array(				'type' => 'text',				'required' => true,				'minlength' => 2,			),			'middlename' => array(				'type' => 'text',			),			'lastname' => array(				'type' => 'text',				'required' => true,				'minlength' => 2,			),			'password' => array(				'type' => 'text',				'required' => false,			),			'email' => array(				'type' => 'text',				'required' => true,				'minlength' => 0,			),			'phone1' => array(				'type' => 'text',				'required' => false,			),			'phone2' => array(				'type' => 'text',				'required' => false,			),			'birthdate' => array(				'type' => 'date',				'required' => false,			),			'group' => array(				'type' => 'dropdown',				'required' => true,				'options' => \app\models\Category::all('category_name <> ?', ''),				'validation' => 'options',			),		);	}}