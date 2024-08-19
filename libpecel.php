<?php

declare(strict_types=1);

class PecelProgram extends PecelFunction {

	public PecelElement $element;

	public function __construct() {
		$this->element = new PecelElement;
		$this->element->line = 0;
		$this->element->column = 0;
	}
}

class PecelElement {

	public string $next_text = "";

	public int $line = 0;
	public int $column = 0;

	public bool $has_next_element = false;
	public PecelElement $next_element;
	public PecelFunction $owner_function;
}

class PecelFunction extends PecelElement {
	public string $name;
	public array $arguments;
	public array $variables;
	public function __construct() {
		$this->arguments = array();
		$this->variables = array();
	}
}

class PecelComment extends PecelElement {}

class PecelVariable extends PecelElement {
	public string $name;
}

class PecelBool extends PecelVariable {
	public bool $value;
}

class PecelInteger extends PecelVariable {
	public int $value;
}

class PecelFloat extends PecelVariable {
	public float $value;
}

class PecelString extends PecelVariable {
	public string $value;
}

class PecelSplitResult {
	public string $text;
	public int $offset;
	public string $separator;
	public string $next_text;
}


/**
 * Create program from string
 **/

function pecel_load($text){

	$pecel = new PecelProgram;

	// set first element

	$pecel->element->next_text = $text;
	$pecel->element->line = 0;
	$pecel->element->column = 0;
	$pecel->element->owner_function = $pecel;

	pecel_set_element($pecel->element);

	return $pecel;
}

function pecel_set_element($element) : bool {

	if ($element->next_text == "") {
		return false;
	}

	// > identify next element
	// > check elements conflict

	$element_types = array();

	if (pecel_is_function($element) == true) {
		array_push($element_types, "PecelFunction");
	}

	if (pecel_is_comment($element) == true) {
		array_push($element_types, "PecelComment");
	}

	if (pecel_is_variable($element) == true) {
		array_push($element_types, "PecelVariable");
	}

	if (count($element_types) > 1) {
		throw new \Exception("Parse Error. Conflict."
			." ".implode(", ", $element_types)
			." line ".$element->line." column ".$element->column
			);
	} elseif (count($element_types) == 0) {
		throw new \Exception("Parse Error."
			."'".substr($element->next_text, 0, 10)."..' is invalid."
			." line ".$element->line." column ".$element->column
			);
	}

	if ($element_types[0] == "PecelFunction") {
		pecel_set_function($element);
	} elseif ($element_types[0] == "PecelComment") {
		pecel_set_comment($element);
	} elseif ($element_types[0] == "PecelVariable") {
		pecel_set_variable($element);
	} else {
		throw new \Exception("Parse Error."
			." element_type is not defined."
			." line ".$element->line." column ".$element->column
			);
	}

	if ($element->has_next_element == false){
		return false;
	}

	pecel_set_element($element->next_element);
	return true;
}

function pecel_load_file($file){
	$text = file_get_contents($file);
	return pecel_load($text);
}

/**
 * Variable declaration.
 * Syntax:
 *  - var<SPACE><NAME><SPACE><TYPE>
 **/

function pecel_is_variable(PecelElement $element){
	if (substr($element->next_text, 0, 4) == "var ") {
		return true;
	}
	return false;
}

function pecel_set_variable(PecelElement $element){

	$name_pattern  = "([a-z]([a-z0-9_]*[a-z0-9])*)";
	$type_pattern  = "(bool|int|float|string|cursor)";
	$value_pattern = "([^ \t]+)";
	// eol - end of line
	$eol_pattern = "[\n]+";

	$name  = null;
	$type  = null;
	$value = null;
	$offset = 0;

	// var i int
	// var i2 int
	// var i_2 int

	$pattern = "/^var"
		." +{$name_pattern}"
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

	// var i int = 0

	$pattern = "/^var"
		." +{$name_pattern}"
		." +{$type_pattern}"
		." *= *{$value_pattern}"
		."{$eol_pattern}"
		."/";

	if (	is_null($name) == true
		&&	$result = preg_match($pattern, $element->next_text, $match)
		) {
		$name = $match[1];
		$type = $match[3];
		$offset = strlen($match[0]);
		$value = $match[4];
	}

	if (is_null($name) == true){
		throw new \Exception("Invalid syntax.");
	}

	if ($type == "int") {
		$variable = new PecelInteger;
	} else {
		throw new \Exception("Invalid type."
			."\n".var_export($match, true)
			);
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

// 	$variable->owner_function->variables->

	$element->has_next_element = true;
	$element->next_element = $variable;
}

// --<SPACE><COMMENT>
// -- this is a comment

function pecel_is_comment(PecelElement $element){
	if (substr($element->next_text, 0, 3) == "-- ") {
		return true;
	}
	return false;
}

function pecel_set_comment(PecelElement $element){

	$comment = new PecelComment;

	$match = pecel_split(array("\n"), $element->next_text);
	$comment->next_text = $match->next_text;
	$comment->owner_function = $element->owner_function;

	$element->has_next_element = true;
	$element->next_element = $comment;
}

// function argument..
// print('Hello World')

function pecel_is_function(PecelElement $element){
	$pattern = "[a-z]([a-z_]*[a-z])*";
	if (preg_match("/^{$pattern}\s*\(/", $element->next_text)) {
		return true;
	}
	return false;
}

function pecel_set_function(PecelElement $element){

	$pattern = "[a-z]([a-z_]*[a-z])*";
	preg_match("/^({$pattern})\s*\(\'([^\']+)\'\)[\r\n]+/", $element->next_text, $match);

	$function = new PecelFunction;
	$function->name = $match[1];
	$argument = $match[3];
	array_push($function->arguments, $argument);
	$function->next_text = substr($element->next_text, strlen($match[0]));
	$function->owner_function = $element->owner_function;

	$element->next_element = $function;
	$element->has_next_element = true;
}

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
	$text = implode(" ", $args);
	echo($text."\n");
	return 0;
}
