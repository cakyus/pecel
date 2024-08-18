<?php

declare(strict_types=1);

class PecelProgram {
	public PecelElement $element;
	public function __construct() {
		$this->element = new PecelElement;
		$this->element->line = 0;
		$this->element->column = 0;
	}
}

class PecelElement {

	public string $next_text;

	public int $line;
	public int $column;

	public bool $has_next_element;
	public PecelElement $next_element;
}

class PecelFunction extends PecelElement {
	public string $name;
	public array $arguments;
	public function __construct() {
		$this->arguments = array();
	}
}

class PecelComment extends PecelElement {}

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

	$element = $pecel->element;
	$element->next_text = $text;
	$element->line = 0;
	$element->column = 0;

	// > identify next element
	// > check elements conflict

	$element_types = array();

	if (pecel_is_function($element) == true) {
		array_push($element_types, "PecelFunction");
	}

	if (pecel_is_comment($element) == true) {
		array_push($element_types, "PecelComment");
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
		$element = pecel_set_function($element);
	} elseif ($element_types[0] == "PecelComment") {
		$element = pecel_set_comment($element);
	} else {
		throw new \Exception("Parse Error."
			." element_type is not defined."
			." line ".$element->line." column ".$element->column
			);
	}

	var_dump($pecel->element);

	return $pecel;
}

function pecel_load_file($file){
	$text = file_get_contents($file);
	return pecel_load($text);
}

function pecel_is_comment($element){
	if (substr($element->next_text, 0, 3) == "-- ") {
		return true;
	}
	return false;
}

// --<SPACE><COMMENT>
// -- this is a comment

function pecel_set_comment($element){

	$match = pecel_split(array("\n"), $element->next_text);

	$comment = new PecelComment;

	$element->next_text = $match->next_text;
	$element->next_element = $comment;
	$element->has_next_element = true;
}

function pecel_is_function($element){
	$pattern = "[a-z]([a-z_]*[a-z])*";
	if (preg_match("/^{$pattern}\s*\(/", $element->next_text)) {
		return true;
	}
	return false;
}

// function argument..
// print('Hello World')

function pecel_set_function($element){

	$pattern = "[a-z]([a-z_]*[a-z])*";
	preg_match("/^({$pattern})\s*\(\'([^\']+)\'\)/", $element->next_text, $match);

	$function = new PecelFunction;
	$function->name = $match[1];
	$argument = $match[3];
	array_push($function->arguments, $argument);

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
	$element = $pecel->element;
	if ($element->has_next_element == true) {
		$element = $element->next_element;
		$element_type = get_class($element);
		if ($element_type == "PecelFunction") {
			pecel_exec_function($element);
		}
	}
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


