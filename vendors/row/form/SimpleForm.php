<?php

namespace row\form;

use row\core\Options;
use row\utils\Inflector;
use row\Output;

abstract class SimpleForm extends \row\Component {

	public $_elements = array(); // internal cache
	abstract protected function elements( $defaults );

	public $options; // typeof Options

	public $defaults = null;
	public $input = array();
	public $errors = array();
	public $output = array();

	public $inlineErrors = true;
	public $elementWrapperTag = 'div';
	public $renderers = array();


	protected function _init() {
		parent::_init();

		if ( $this->options->_exists('defaults') ) {
			$this->defaults = $this->options->defaults;
		}
	}

	protected function _pre_validation() {}
	protected function _post_validation() {}
	protected function _post_validation_failed() {}


	public function validate( $data ) {
		$this->input = $data;

		$elements =& $this->useElements();
		$validators = $output = $storage = array();

		$this->_fire('pre_validate');

		function murlencode($name, $k, $arr) {
			$out = array();
			foreach ( $arr AS $k2 => $v ) {
				$out[] = $name . '['.$k.']['.$k2.']=' . urlencode((string)$v);
			}
			return implode('&', $out);
		}

		foreach ( $elements AS $name => &$element ) {
			$element['_name'] = $name;
			$this->elementTitle($element);

			// named form element
			if ( is_string($name) ) {
				if ( !empty($element['required']) ) {
					if ( !$this->validateRequired($this, $name) ) {
						$this->errors[$name][] = $this->errorMessage('required', $element);
					}
				}
				if ( !empty($element['regex']) && empty($this->errors[$name]) ) {
					$match = preg_match('/^'.$element['regex'].'$/', (string)$this->input($name, ''));
					if ( !$match ) {
						$this->errors[$name][] = $this->errorMessage('regex', $element);
					}
				}
				if ( !empty($element['validation']) && empty($this->errors[$name]) && '' !== $this->input($name, '') ) {
					$fn = $element['validation'];
					if ( is_string($fn) ) {
						$validationFunction = 'validate'.ucfirst($fn);
						$fn = array($this, $validationFunction);
					}
					if ( is_callable($fn) ) {
						$r = call_user_func($fn, $this, $name);
						if ( false === $r || is_string($r) ) {
							$this->errors[$name][] = false === $r ? ( isset($element['error']) ? $element['error'] : $this->errorMessage('custom', $element) ) : $r;
						}
					}
				}
				if ( isset($this->input[$name]) ) {
					$elName = $this->elementName($element);
					if ( array_key_exists($name, $this->output) ) {
						$input = $this->output[$name];
					}
					else {
						$input = $this->input[$name];
					}

					$elStorage = isset($element['storage']) ? (string)$element['storage'] : 'default';
					isset($storage[$elStorage]) or $storage[$elStorage] = array();
					$storage[$elStorage][] = $elName;

					foreach ( (array)$input AS $k => $v ) {
						$output[] = is_array($v) ? murlencode($elName, $k, $v) : $elName . '=' . urlencode((string)$v);
					}
				}
			}

			// extra validator
			else if ( isset($element['validation']) ) {
				// must have fields connection
				if ( isset($element['fields']) ) {
					$validators[] = $element;
				}
			}

			unset($element);
		}

		// output -> array -> into $this
		$output = implode('&', $output);
		$this->output = array();
		parse_str($output, $this->output);

		// split output array
		$this->output = $this->splitOutput($storage);

		$noErrors = empty($this->errors);
		// check extra (field agnostic) validators
		foreach ( $validators AS $validator ) {

			// require previous validation for some fields?
			$require = isset($validator['require']) ? (array)$validator['require'] : null;

			// do this validator always, independant of previous/rest validation
			$always = !empty($validator['always']);

			// Always or No field errors so far
			if ( $always || $noErrors ) {

				// requirements met?
				if ( empty($require) || !array_intersect($require, array_keys($this->errors)) ) {

					// validator must be class method or Closure
					$v = $validator['validation'];
					$v = is_string($v) ? array($this, $v) : $v;

					// execute validator function
					$r = $v($this);

					// false for failed with standard message, String for failed with response as message
					if ( false === $r || is_string($r) ) {

						// error message
						$error = false === $r ? $this->errorMessage('custom', $validator) : $r;

						// show message for these fields
						is_array($validator['fields']) or $validator['fields'] = explode(',', $validator['fields']);
						foreach ( $validator['fields'] AS $name ) {
							$this->errors[trim($name)][] = $error;
						}

					} // validation response

				} // validator's requirements

			} // Always || NoErrors

		} // extra validators

		if ( 0 == count($this->errors) ) {
			$this->_fire('post_validation');
			return true;
		}

		$this->_fire('post_validation_failed');

		return false;
	}

	public function validateOptions( $form, $name ) {
		$elements =& $this->useElements();
		$element = $elements[$name];
		$value = $this->input($name, '');

		$options = $element['options'];
		foreach ( $options AS $k => $v ) {
			if ( $this->getOptionValue($k, $v) == $value ) {
				return true;
			}
		}
		return false;
	}

	public function validateEmail( $form, $name ) {
		$value = $form->input($name);
		return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
	}

	public function validateDate( $form, $name ) {
		$value = $form->input($name);
		if ( 0 >= preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)$/', $value, $match) ) {
			return false;
		}
		$date = $match[1] . '-' . lpad($match[2]) . '-' . lpad($match[3]);
		$this->output($name, $date);
	}

	public function validateRequired( $form, $name ) {
		if ( !isset($this->input[$name]) ) {
			return false;
		}

		$elements = $this->useElements();
		$element = $elements[$name];

		$length = is_array($this->input[$name]) ? count($this->input[$name]) : strlen(trim((string)$this->input[$name]));

		$minlength = isset($element['minlength']) ? (int)$element['minlength'] : 1;
		$maxlength = isset($element['maxlength']) ? (int)$element['maxlength'] : 0;

		return $length >= $minlength && ( !$maxlength || $maxlength >= $length );
	}

	public function validateUnique( $form, $name ) {
		$elements = $this->useElements();
		$element = $elements[$name];

		if ( !isset($element['unique'], $element['unique']['model'], $element['unique']['field']) ) {
			// not enough information: autofail
			return false;
		}

		$conditions = isset($element['unique']['conditions']) ? (array)$element['unique']['conditions'] : array();
		$model = $element['unique']['model'];
		$field = $element['unique']['field'];

		$db = $model::dbObject();
		$params = isset($conditions[1]) ? $conditions[1] : array();
		$conditions = $db->replaceholders($conditions[0], $params);
		$conditions .= ' AND '.$db->stringifyConditions(array($field => $this->input($name)));

		$exists = $model::count($conditions);
		return !$exists;
	}

	public function validateCSV( $form, $name ) {
		$value = trim($this->input($name, ''));
		$pattern = 'a-z1-9\- ';
		return 0 < preg_match('/^['.$pattern.']+(?:, ?['.$pattern.']+)*$/', $value);
	}

	public function validateNumber( $form, $name ) {
		return is_numeric($this->input($name));
	}


	public function errorMessage( $type, $element ) {
		$this->elementTitle($element);
		$title = $element['title'];

		$quote = function($value) {
			return $value;
		};

		switch ( $type ) {
			case 'required':
				return 'Field '.$quote($title).' is required';
			break;
			case 'regex':
				return 'Field '.$quote($title).' has invalid format';
			break;
			case 'custom':
				$fields = !isset($element['fields']) ? array($element['_name']) : $element['fields'];
				is_array($fields) or $fields = explode(',', $fields);
				foreach ( $fields AS &$name ) {
					$name = $this->_elements[trim($name)]['title'];
					unset($name);
				}
				return isset($element['message']) ? $element['message'] : 'Validation failed for: '.implode(', ', array_map($quote, $fields));
			break;
		}

		return 'Unknown type of error for field "'.$title.'".';
	}

	public function errors() {
		$errors = array();
		foreach ( $this->errors AS $errs ) {
			$errors = array_merge($errors, array_filter($errs));
		}
		return array_unique($errors);
	}

	public function error( $elementName, $error = ' error', $noError = ' no-error' ) {
		return isset($this->errors[$elementName]) ? $error : $noError;
	}


	public function input( $name, $alt = '' ) {
		$elements = $this->useElements();

		// check input (probably POST) data
		if ( isset($this->input[$name]) ) {
			return $this->input[$name];
		}

		// check default form values
		$dv = (array)$this->defaults;
		if ( isset($dv[$name]) ) {
			return $dv[$name];
		}

		// check default element value
		if ( isset($elements[$name], $elements[$name]['default']) ) {
			return $elements[$name]['default'];
		}

		// no input found: return alt
		return $alt;
	}

	public function output( $name, $value = null ) {
		if ( 1 < func_num_args() ) {
			return $this->output[$name] = $value;
		}
		return isset($this->output[$name]) ? $this->output[$name] : '';
	}

	public function splitOutput( $lists ) {
		$output = array();
		foreach ( $lists AS $listName => $fields ) {
			$output[$listName] = array();
			is_array($fields) or $fields = explode(',', $fields);
			foreach ( $fields AS $fieldName ) {
				if ( array_key_exists($fieldName, $this->output) ) {
					$output[$listName][$fieldName] = $this->output[$fieldName];
				}
			}
		}
		return $output;
	}



	public function renderGridElement( $name, $element, $wrapper = true ) {
		$html = "\n".'<table class="grid">'."\n";
		$html .= '	<tr>'."\n";
		$html .= '		<th class="corner"></th>'."\n";
		foreach ( $element['horizontal'][1] AS $k => $hLabel ) {
			$html .= '		'.$this->renderGridHorizontalTH($element, $hLabel)."\n";
		}
		$html .= '	</tr>'."\n";
		foreach ( $element['vertical'][1] AS $vKey => $vLabel ) {
			$vKey = $this->getOptionValue($vKey, $vLabel);
			$html .= '	<tr>'."\n";
			$html .= '		'.$this->renderGridVerticalTH($element, $vLabel)."\n";
			foreach ( $element['horizontal'][1] AS $hKey => $hLabel ) {
				$hKey = $this->getOptionValue($hKey, $hLabel);
				$sub = '?';
				$fn = 'renderGrid'.$element['subtype'];
				if ( is_callable($method = array($this, $fn)) ) {
					list($xValue, $yValue) = empty($element['reverse']) ? array($vKey, $hKey) : array($hKey, $vKey);
//					$sub = $this->$fn($name.'__'.$xValue.'[]', $yValue);
					$sub = $this->$fn($element, $xValue, $yValue);
				}
				$html .= '		<td>'.$sub.'</td>'."\n";
			}
			$html .= '	</tr>'."\n";
		}
		$html .= '</table>'."\n";

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapperWithTitle($html, $element);
	}

	protected function renderGridHorizontalTH( $element, $label ) {
		$html = '<th class="horizontal">'.$label.'</th>';
		return $html;
	}

	protected function renderGridVerticalTH( $element, $label ) {
		$html = '<th class="vertical">'.$label.'</th>';
		return $html;
	}

	protected function renderGridOptions( $element, $xValue, $yValue ) {
		$name = $element['_name'];
		$options = $element['options'];
		$dummy = isset($element['dummy']) ? $element['dummy'] : '';

		$input = $this->input($name, array());
		$value = isset($input[$xValue][$yValue]) ? $input[$xValue][$yValue] : '';

		$elName = $name."[$xValue][$yValue]";
		$html = $this->renderSelect($elName, $options, $value, $dummy);

		return $html;
	}

	protected function renderGridCheckbox( $element, $xValue, $yValue ) {
		$name = $element['_name'];

		$input = $this->input($name, array());
		$value = isset($input[$xValue]) ? (array)$input[$xValue] : array();
		$checked = in_array($yValue, $value) ? ' checked' : '';

		$html = '<input type="checkbox" name="'.$name.'['.$xValue.'][]" value="'.$yValue.'"'.$checked.' />';

		return $html;
	}

/*	public function renderGridCheckboxElement( $elementName, $elementValue ) {
		$formValue = $this->input(trim($elementName, ']['), array());
		$checked = in_array($elementValue, $formValue) ? ' checked' : '';
		$html = '<input type="checkbox" name="'.$elementName.'" value="'.$elementValue.'"'.$checked.' />';

		return $html;
	}*/


	public function renderRadioElement( $name, $element, $wrapper = true ) {
		$type = $element['type'];
		$elName = $name;
		$checked = $this->input($name, null);

		$options = array();
		foreach ( (array)$element['options'] AS $k => $v ) {
			$k = $this->getOptionValue($k, $v);
			$options[] = '<span class="option"><label><input type="radio" name="'.$elName.'" value="'.$k.'"'.( $checked === $k ? ' checked' : '' ).' /> '.$v.'</label></span>';
		}
		$html = implode(' ', $options);

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapperWithTitle($html, $element);
	}

	public function renderCheckboxElement( $name, $element, $wrapper = true ) {
		$o = Output::$class;

		$elName = $name;
		$checked = null !== $this->input($name, null) ? ' checked' : '';
		$value = isset($element['value']) ? ' value="'.$o::html($element['value']).'"' : '';

		$input = '<label><input type="checkbox" name="'.$elName.'"'.$value.$checked.' /> '.$element['title'].'</label>';
		$html = '<span class="input">'.$input.'</span>';
		if ( !empty($element['description']) ) {
			$html .= ' ';
			$html .= '<span class="description">'.$element['description'].'</span>';
		}

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapper($html, $element);
	}

	public function renderCheckboxesElement( $name, $element, $wrapper = true ) {
		$elName = $name.'[]';
		$checked = (array)$this->input($name, array());

		$options = array();
		foreach ( $element['options'] AS $k => $v ) {
			$k = $this->getOptionValue($k, $v);
			$options[] = '<span class="option"><label><input type="checkbox" name="'.$elName.'" value="'.$k.'"'.( in_array($k, $checked) ? ' checked' : '' ).' /> '.$v.'</label></span>';
		}
		$html = implode(' ', $options);

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapperWithTitle($html, $element);
	}


	public function renderDropdownElement( $name, $element, $wrapper = true ) {
		return $this->renderOptionsElement($name, $element, $wrapper);
	}

	public function renderOptionsElement( $name, $element, $wrapper = true ) {
		$value = $this->input($name, 0);
		$elName = $name;

		$dummy = isset($element['dummy']) ? $element['dummy'] : '';
		$html = $this->renderSelect($elName, $element['options'], $value, $dummy);

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapperWithTitle($html, $element);
	}

	protected function renderSelect( $elName, $options, $value = '', $dummy = '' ) {
		$html = '<select name="'.$elName.'">';
		if ( $dummy ) {
			$html .= '<option value="'.$this->getDummyOptionValue().'">'.$dummy.'</option>';
		}
		foreach ( (array)$options AS $k => $v ) {
			$k = $this->getOptionValue($k, $v);
			$html .= '<option value="'.$k.'"'.( (string)$k === $value ? ' selected' : '' ).'>'.$v.'</option>';
		}
		$html .= '</select>';
		return $html;
	}

	protected function getDummyOptionValue( $element = null ) {
		return '';
	}

	protected function getOptionValue( $k, $v ) {
		if ( is_a($v, '\row\database\Model') ) {
			$k = implode(',', $v->_pkValue());
		}
		return $k;
	}

	public function renderTextElement( $name, $element, $wrapper = true ) {
		$type = $element['type'];
		$elName = $name;
		$value = $this->input($name);

		$maxlength = isset($element['maxlength']) ? ' maxlength="'.(int)$element['maxlength'].'"' : '';

		$html = '<input type="'.$type.'" name="'.$elName.'" value="'.$value.'"'.$maxlength.$this->elementAttributes($element).' />';

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapperWithTitle($html, $element);
	}

	public function renderTextareaElement( $name, $element, $wrapper = true ) {
		$value = $this->input($name);
		$elName = $name;

		$options = Options::make($element);

		$rows = $options->rows ? ' rows="'.$options->rows.'"' : '';
		$cols = $options->cols ? ' cols="'.$options->cols.'"' : '';

		$html = '<textarea'.$rows.$cols.' name="'.$elName.'">'.$value.'</textarea>';

		if ( !$wrapper ) {
			return $html;
		}

		return $this->renderElementWrapperWithTitle($html, $element);
	}

	public function renderMarkupElement( $name, $element ) {
		$options = Options::make(Options::make($element));

		$inside = $options->inside ?: $options->text;
		if ( is_callable($inside) ) {
			$inside = $inside($this);
		}

		if ( $inside ) {
			return '<'.$this->elementWrapperTag.' class="form-element markup '.$name.'">'.$inside.'</'.$this->elementWrapperTag.'>';
		}
		else if ( $options->outside ) {
			$outside = is_callable($options->outside) ? call_user_func($options->outside, $this) : $options->outside;
			if ( $outside ) {
				return $outside;
			}
		}

		return '';
	}



	public function &useElements() {
		if ( !$this->_elements ) {
			if ( !is_a($this->defaults, 'row\\database\\Model') && !is_a($this->defaults, 'row\\core\\Options') ) {
				$this->defaults = Options::make((array)$this->defaults);
			}
			$elements = array();
			$index = 0;
			foreach ( $this->elements($this->defaults) AS $name => $element ) {
				$element['_name'] = $name;
				$element['_index'] = $index++;
				$this->elementTitle($element);
				$elements[$name] = $element;
			}
			$this->_elements = $elements;
		}
		return $this->_elements;
	}

	public function render( $withForm = true, $options = array() ) {
		$o = Output::$class;

		if ( is_array($withForm) ) {
			$options = $withForm;
			$withForm = true;
		}

		$options = $this->options->extend($options);
		$elements = $this->useElements();

		// Render 1 element?
		if ( is_string($withForm) ) {
			// First argument is element name, so render only that element
			if ( isset($this->_elements[$withForm]) ) {
				return $this->renderElement($withForm, $this->_elements[$withForm]);
			}

			// Element doesn't exist, so return that
			return '';
		}

		$index = 0;
		$html = '';
		foreach ( $elements AS $name => $element ) {
			if ( is_string($name) || ( isset($element['type']) && in_array($element['type'], array('markup')) ) ) {
				$html .= $this->renderElement($name, $element);
				$html .= $this->elementSeparator();
			}
		}

		if ( $withForm ) {
			$method = $options->get('method', 'post');
			$action = $o::url($this->application->uri);
			$html =
				'<form method="'.$method.'" action="'.$action.'"'.$o::attributes($options, array('method')).'>' .
					$this->elementSeparator() .
					$html.$this->renderButtons() .
					$this->elementSeparator() .
				'</form>';
		}

		return $html;
	}

	public function renderElement( $name, $element ) {
		// centrally assigned renderer for this element
		if ( isset($this->renderers[$name]) ) {
			$renderer = $this->renderers[$name];
			// Closure or class method
			if ( is_callable($fn = $renderer) || is_callable($fn = array($this, (string)$renderer)) ) {
				return call_user_func($fn, $name, $element, $this);
			}
		}

		if ( empty($element['type']) ) {
			return '';
		}
		$type = $element['type'];

		// special render method in class extension: only if a custom renderer wasn't defined
		if ( !isset($element['render']) ) {
			if ( method_exists($this, 'renderElement_'.$name) ) {
				$element['render'] = 'renderElement_'.$name;
			}
		}
		if ( isset($element['render']) ) {
			$fn = $element['render'];
			if ( is_string($fn) ) {
				$fn = array($this, $fn);
			}
			return call_user_func($fn, $name, $element, $this);
		}

		$fn = 'render'.ucfirst($type).'Element';
		if ( !$this->_callable($fn) ) {
			$fn = 'renderTextElement';
		}

		return $this->$fn($name, $element);
	}

	protected function elementAttributes( $element ) {
		$o = Output::$class;

		if ( isset($element['attributes']) ) {
			return $o::attributes($element['attributes']);
		}
	}

	public function elementName( $element ) {
		$name = $element['_name'];
		if ( isset($element['name']) ) {
			$name = $element['name'];
		}
		else if ( isset($element['type']) && in_array($element['type'], array('checkboxes')) ) {
			$name .= '[]';
		}
		return $name;
	}

	public function renderButtons() {
		$o = Output::$class;

		$submit = $this->submitButtonText();
		$submit && $submit = ' value="'.$o::html($submit).'"';

		return '<'.$this->elementWrapperTag.' class="form-submit"><input type="submit"'.$submit.' /></'.$this->elementWrapperTag.'>';
	}

	public function submitButtonText() {
		return '';
	}

	public function renderElementWrapper( $html, $element ) {
		$name = $element['_name'];
		return '<'.$this->elementWrapperTag.' class="'.$this->elementWrapperClasses($element).'">'.$html.'</'.$this->elementWrapperTag.'>';
	}

	public function elementWrapperClasses( $element, $string = true ) {
		$name = $element['_name'];

		$classes = array('form-element', $element['type'], $name, trim($this->error($name)));

		return $string ? implode(' ', $classes) : $classes;
	}

	public function renderElementWrapperWithTitle( $input, $element ) {
		$name = $element['_name'];

		$description = empty($element['description']) ? '' : '<span class="description">'.$element['description'].'</span>';
		$error = $this->inlineErrors && isset($this->errors[$name]) ? '<span class="error">'.$this->errors[$name][0].'</span>' : '';

		$html = '<label>'.$element['title'].'</label><span class="input">'.$input.'</span>'.$error.$description;

		return $this->renderElementWrapper($html, $element);
	}

	public function elementSeparator() {
		return "\n\n";
	}



	public function elementTitle( &$element ) {
		if ( empty($element['title']) ) {
			$element['title'] = $this->nameToTitle($element['_name']);
		}
	}

	public function nameToTitle( $name ) {
		return Inflector::spacify($name); // 'beautify'
	}



	public function __tostring() {
		return $this->render();
	}


	public function __get( $key ) {
		return null;
	}


}


