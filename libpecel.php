<?php

declare(strict_types=1);

class PecelText {

	public string $value;

	public int $position;
	public int $length;

	public int $line;
	public int $column;
}

/**
 * Program.
 **/

class PecelProgram extends PecelFunction {

}

class PecelElement {

	public PecelFunction $owner_function;

	public PecelElement $next_element;

	public bool $has_next_element = false;

	public int $line = 0;
	public int $column = 0;
}

class PecelFunction extends PecelElement {

	public string $name;

	public array $arguments;
	public array $variables;
}

class PecelComment extends PecelElement {}

class PecelValue {

	/**
	 * @var $type string
	 * Types:
	 *  - bool
	 *  - int
	 *  - float
	 *  - string
	 **/

	public string $type;

	/**
	 * @var $value mixed
	 **/

	public $value;

	/**
	 * @var $text string
	 * The whole string have been parsed.
	 **/

	public $text;
}

/**
 * Variable declaration.
 **/

class PecelVariable extends PecelElement {
	public string $name;
}

/**
 * Variable declaration for integer.
 **/

class PecelInteger extends PecelVariable {
	public int $value;
}

/**
 * Variable declaration for float.
 **/

class PecelFloat extends PecelVariable {
	public float $value;
}

/**
 * Variable declaration for string.
 **/

class PecelString extends PecelVariable {
	public string $value;
}

/**
 * Variable declaration for boolean.
 **/

class PecelBoolean extends PecelVariable {
	public string $value;
}

class PecelAssignment extends PecelElement {
	public string $variable_name;
}

class PecelMatch {
	public string $value;
	public string $text;
}

class PecelSplitResult {
	public string $text;
	public int $offset;
	public string $separator;
	public string $next_text;
}

class PecelPattern {

	// value pattern defined in pecel_get_value
	// due to complexity of string value

	// "{}": "curly"

	const WORD         = '([a-z]+)';
	const SPACE        = '([ \r\n\t]+)';
	const VARIABLE     = '([a-z]([a-z0-9_]*[a-z0-9])*)';
	const TYPE         = '(int|float|string|bool|array|cursor)';
	const SYMBOL       = '([\(\)\[\]\,])';
	const ROUND_OPEN   = '(\()';
	const ROUND_CLOSE  = "(\))";
	const SQUARE_OPEN  = '(\[)';
	const SQUARE_CLOSE = '(\])';
}

/**
 * Create program from string
 **/

function pecel_load(string $string){

	$text = new PecelText;
	$text->value    = $string;
	$text->position = 0;
	$text->length   = strlen($string);
	$text->line     = 0;
	$text->column   = 0;

	$function = new PecelProgram;
	$function->arguments = array();
	$function->variables = array();

	$element = new PecelElement;
	$element->owner_function = $function;

	$function->element = $element;

	pecel_set_element($element, $text);

	return $function;
}

/**
 * Set next element.
 **/

function pecel_set_element(PecelElement $element, PecelText $text) : bool {

	if ($text->length == 0) {
		return false;
	}

	if ($text->position == $text->length) {
		return false;
	}

	// > identify next element
	// > check elements conflict

	$element_types = array();

	if (pecel_is_comment($text) == true) {
		array_push($element_types, "PecelComment");
	}

	// variable assignment

	if (pecel_is_assigment($text) == true) {
		array_push($element_types, "PecelAssignment");
	}

	// function declaration

	if (pecel_is_function($text) == true) {
		array_push($element_types, "PecelFunction");
	}

	// variable declaration

	if (pecel_is_variable($text) == true) {
		array_push($element_types, "PecelVariable");
	}

	if (count($element_types) > 1) {
		throw new \Exception("Parse Error. Conflict."
			." ".implode(", ", $element_types)
			." line ".$element->line." column ".$element->column
			);
	} elseif (count($element_types) == 0) {
		throw new \Exception("Parse Error."
			."'".substr($text->value, 0, 10)."..' is invalid."
			." line ".$text->line." column ".$text->column
			);
	}

	if ($element_types[0] == "PecelFunction") {
		pecel_set_function($element, $text);
	} elseif ($element_types[0] == "PecelComment") {
		pecel_set_comment($element, $text);
	} elseif ($element_types[0] == "PecelVariable") {
		pecel_set_variable($element, $text);
	} elseif ($element_types[0] == "PecelAssignment") {
		pecel_set_assignment($element, $text);
	} else {
		throw new \Exception("Parse Error."
			." element_type is not defined."
			." line ".$element->line." column ".$element->column
			);
	}

	if ($element->has_next_element == false){
		return false;
	}

	pecel_set_element($element->next_element, $text);

	return true;
}

function pecel_load_file($file){
	$text = file_get_contents($file);
	return pecel_load($text);
}

function pecel_seek(PecelText $text, int $position) {
	$text->position = $position;
}

function pecel_get_word(PecelText $text) : PecelMatch | bool {

	$word = PecelPattern::WORD;
	$space = PecelPattern::SPACE;

	$string = substr($text->value, $text->position);

	if (preg_match("/^{$word}{$space}*/", $string, $m) == false) {
		return false;
	}

	$match = new PecelMatch;
	$match->value = $m[1];
	$match->text  = $m[0];

	$text->position = $text->position + strlen($match->text);

	return $match;
}

function pecel_get_symbol(PecelText $text) : PecelMatch | bool {

	$symbol = PecelPattern::SYMBOL;
	$space = PecelPattern::SPACE;

	$string = substr($text->value, $text->position);

	if (preg_match("/^{$symbol}{$space}*/", $string, $m) == false) {
		return false;
	}

	$match = new PecelMatch;
	$match->value = $m[1];
	$match->text  = $m[0];

	$text->position = $text->position + strlen($match->text);

	return $match;
}

/**
 * Get value.
 *
 * - 123      => integer
 * - 123.45   => float
 * - '123.45' => string, single_quote
 * - "123.45" => string, double_qoute
 * - true     => bool
 * - false    => bool
 **/

function pecel_get_value(PecelText $text) : PecelValue | bool {

	$string = substr($text->value, $text->position);

	$type = "unknown";

	if (substr($string, 0, 1) == "'") {
		$type = "single_quote";
		$separator = "'";
	} elseif (substr($string, 0, 1) == '"') {
		$type = "double_quote";
		$separator = '"';
	}

	if ($type == "unknown") {
		throw new \Exception("Type is unknown.");
	}

	// single_quote
	// double_quote

	if (	$type == "single_quote"
		||	$type == "double_quote"
		) {

		$length  = strlen($string);
		$value   = "";
		$value_text = $separator;
		$has_eof = false;

		for ($i = 1; $i < $length; $i++) {

			$char = substr($string, $i, 1);
			if ($char != $separator) {
				$value .= $char;
				$value_text .= $char;
				continue;
			}

			// 'Hello ''World'' !'
			//
			// when char is separator
			//   then get next char
			// when next char is separator
			//   then jump to after next char
			// when next char is space or symbol
			//   then mark end of string

			// 'Hello'$

			$j = $i + 1;
			if ($j == $length) {
				$has_eof = true;
				$value_text .= $char;
				break;
			}

			// 'Hello',

			$char_j = substr($string, $j, 1);
			if ($char_j != $separator) {
				$value_text .= $char;
				$has_eof = true;
				break;
			}

			// 'Hello ''W
			$k = $j + 1;
			$char_k = substr($string, $k, 1);
			$value .= $char.$char_k;
			$value_text .= $char.$char_j.$char_k;
			$i = $k;
		}

		if ($has_eof == false) {
			var_dump($value);
			var_dump($value_text);
			var_dump($string);
			throw new \Exception("String not terminated.");
		}

		$object = new PecelValue;
		$object->type = "string";
		$object->value = $value;
		$object->text = $value_text;

		$text->position = $text->position + strlen($value_text);

		return $object;
	}

	// float
	// integer
	// bool

	var_dump($type);
	trigger_error("_", E_USER_NOTICE); exit();

	trigger_error("_", E_USER_NOTICE); exit();

	trigger_error("_", E_USER_NOTICE); exit();


	if (preg_match("/^($value)$space/", $string, $m) == true) {
	var_dump($m);
		trigger_error("_", E_USER_NOTICE); exit();
		return false;
	}

	trigger_error("_", E_USER_NOTICE); exit();

	$match = new PecelMatch;
	$match->value = $m[1];
	$match->text  = $m[0];

	$text->position = $text->position + strlen($match->text);

	return $match;
}

/**
 * Variable declaration.
 * Syntax:
 *  - var<SPACE><NAME><SPACE><TYPE>
 **/

function pecel_is_variable(PecelText $text) : bool {

	$position = $text->position;

	$match = pecel_get_word($text);
	if ($match == false) {
		pecel_seek($text, $position);
		return false;
	}

	if ($match->value != "var") {
		pecel_seek($text, $position);
		return false;
	}

	pecel_seek($text, $position);
	return true;
}

function pecel_set_variable(PecelElement $element, PecelText $text){

	$variable = PecelPattern::VARIABLE;
	$type     = PecelPattern::TYPE;

	$name  = null;
	$type  = null;

	// var i int

	$match = pecel_get_word($text);

	$pattern = "/^var+{$name_pattern}"
		." +{$type_pattern}"
		."{$eol_pattern}"
		."/";

	if (	is_null($name) == true
		&&	$result = preg_match($pattern, $element->next_text, $match)
		) {
		$name = $match[1];
		$type = $match[3];
		$offset = strlen($match[0]);
	}

	if (is_null($name) == true){
		throw new \Exception("Invalid syntax. {$element->next_text}");
	}

	if ($type == "int") {
		$variable = new PecelInteger;
	} elseif ($type == "float") {
		$variable = new PecelFloat;
	} elseif ($type == "string") {
		$variable = new PecelString;
	} elseif ($type == "bool") {
		$variable = new PecelBoolean;
	} else {
		throw new \Exception("Invalid type."
			."\n".var_export($match, true)
			);
	}

	// check duplicated variable declaration
	foreach ($element->owner_function->variables as $v) {
		if ($v->name == $name) {
			throw new \Exception("'{$name}' already defined in line {$v->line}");
		}
	}

	$variable->name = $name;
	if (is_null($value) == false) {
		if ($type == "int") {
			$variable->value = intval($value);
		} else {
			throw new \Exception("Type '{$type}' is invalid.");
		}
	}
	$variable->next_text = substr($element->next_text, $offset);
	$variable->owner_function = $element->owner_function;

	array_push($variable->owner_function->variables, $variable);

	$element->has_next_element = true;
	$element->next_element = $variable;
}

function pecel_is_comment(PecelText $text){
	if (substr($text->value, 0, 3) == "-- ") {
		return true;
	}
	return false;
}

// - begin with "-- "
// - end with LF or EOF

function pecel_set_comment(PecelElement $element){

	$comment = new PecelComment;

	$match = pecel_split(array("\n"), $element->next_text);
	$comment->next_text = $match->next_text;
	$comment->owner_function = $element->owner_function;

	$element->has_next_element = true;
	$element->next_element = $comment;
}


function pecel_is_function(PecelText $text){

	// print('Hello World')

	// save original position
	$position = $text->position;

	$match = pecel_get_word($text);
	if ($match == false) {
		pecel_seek($text, $position);
		return false;
	}

	$match = pecel_get_symbol($text);
	if ($match == false) {
		pecel_seek($text, $position);
		return false;
	}

	if ($match->value != "(") {
		pecel_seek($text, $position);
		return false;
	}

	pecel_seek($text, $position);
	return true;
}

function pecel_set_function(PecelElement $element, PecelText $text){

	$function = new PecelFunction;
	$function->owner_function = $element->owner_function;
	$function->arguments = array();
	$function->variables = array();

	// print('Hello World')
	// print(variable)

	$match = pecel_get_word($text);
	$function->name = $match->value;

	// symbol "("
	$match = pecel_get_symbol($text);

	// symbol ")"
	$match = pecel_get_symbol($text);
	if ($match) {
		if ($match->value != ")") {
			throw new \Exception("Expect symbol ')'.");
		}
	} else {

		while (true) {

			// print('Hello')
			$value = pecel_get_value($text);
			if ($value) {
				array_push($function->arguments, $value);
			} else {
				var_dump($value);
				var_dump($text);
				var_dump(substr($text->value,$text->position));
				throw new \Exception("_");
			}

			$symbol = pecel_get_symbol($text);

			if ($symbol) {
				if ($symbol->value == ")") {
					break;
				} elseif ($symbol->value == ",") {
					continue;
				} else {
					throw new \Exception("Expect symbol ',' or ')'. Got '{$symbol->value}'.");
				}
			} else {
				var_dump($value);
// 				var_dump($text);
// 				var_dump(substr($text->value,$text->position));
				throw new \Exception("_");
			}
		}
	}

	$element->next_element = $function;
	$element->has_next_element = true;
}

function pecel_is_assigment(PecelText $text) : bool {

	$variable = PecelPattern::VARIABLE;
	$space = PecelPattern::SPACE;

	if (preg_match("/^{$variable}{$space}*=/", $text->value, $match) == false) {
		return false;
	}

	return true;
}

function pecel_set_assignment(PecelElement $element){

	$name = PecelPattern::VARIABLE_NAME;
	$space = PecelPattern::SPACE;

	preg_match("/^{$name}{$space}*=/", $element->next_text, $match);

	var_dump($match);
	trigger_error("here", E_USER_NOTICE); exit();

	$assignment = new PecelAssignment;
	$function->name = $match[1];
	$argument = $match[3];
	array_push($function->arguments, $argument);
	$function->next_text = substr($element->next_text, strlen($match[0]));
	$function->owner_function = $element->owner_function;

	$element->next_element = $function;
	$element->has_next_element = true;
}

/**
 * Split string by separators.
 * The result is PecelSplitResult with the nearest separator.
 * Return false when no separator found.
 **/

function pecel_split(array $separators, string $text) : PecelSplitResult | bool {

	// find matches

	$result = false;

	foreach ($separators as $separator){

		$offset = strpos($text, $separator);
		if ($offset === false){
			continue;
		}

		if ($result === false) {
			$result = new PecelSplitResult;
		} elseif ($offset > $result->offset) {
			continue;
		}

		$result->text = substr($text, 0, $offset);
		$result->offset = $offset;
		$result->separator = $separator;
		$result->next_text = substr($text, $offset + strlen($separator));
	}

	return $result;
}

/**
 * Execute program
 **/

function pecel_exec(PecelProgram $pecel){
	if ($pecel->element->has_next_element == false) {
		return false;
	}
	pecel_exec_element($pecel->element->next_element);
}

function pecel_exec_element(PecelElement $element){

	$element_type = get_class($element);

	if ($element_type == "PecelFunction") {
		pecel_exec_function($element);
	} elseif ($element_type == "PecelComment") {
		// do nothing
	} elseif ($element_type == "PecelInteger") {
		// do nothing
	} elseif ($element_type == "PecelString") {
		// do nothing
	} elseif ($element_type == "PecelBoolean") {
		// do nothing
	} else {
		throw new \Exception("Element type '{$element_type}' is not supported.");
	}

	if ($element->has_next_element == false) {
		return false;
	}

	pecel_exec_element($element->next_element);
	return true;
}

function pecel_exec_function(PecelFunction $function){
	$function_name = $function->name;
	if ($function_name == "print"){
		$function_name = "pecel_print";
	} else {
		throw new \Exception("Function '{$function_name}' is not defined.");
	}
	$function->value = call_user_func_array($function_name, $function->arguments);
}

function pecel_print(){

	$args = func_get_args();
	foreach ($args as $k => $v) {

		$type = gettype($v);

		if ($type == "object"){
			$class = get_class($v);
			if ($class == "PecelValue") {
				$v = $v->value;
			}
			$type = gettype($v);
		}

		if ($type == "string") {
			$args[$k] = $v;
		} else {
			throw new \Exception("Type '{$type}' is not supported.");
		}
	}

	$text = implode(" ", $args);
	echo($text."\n");
	return 0;
}
