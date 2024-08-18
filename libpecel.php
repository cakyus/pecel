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

	$element_type = "PecelElement";

	if (pecel_is_function($element) == true) {
		$next_element_type = "PecelFunction";
		if ($element_type == "PecelElement") {
			$element_type = $next_element_type;
		} else {
			throw new \Exception("Parse Error. Conflict."
				." {$element_type} {$next_element_type}"
				." line ".$element->line." column ".$element->column
				);
		}
	}

	if ($element_type == "PecelElement") {
		throw new \Exception("Parse Error."
			."'".substr($element->next_text, 0, 10)."..' is invalid."
			." line ".$element->line." column ".$element->column
			);
	}

	if ($next_element_type == "PecelFunction") {
		$element = pecel_set_function($element);
	}

	return $pecel;
}

function pecel_load_file($file){
	$text = file_get_contents($file);
	return pecel_load($text);
}

// function argument..
// print('Hello World')

function pecel_is_function($element){
	$pattern = "[a-z]([a-z_]*[a-z])*";
	if (preg_match("/^{$pattern}\s*\(/", $element->next_text)) {
		return true;
	}
	return false;
}

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
	}
	$function->value = call_user_func_array($function_name, $function->arguments);
}

function pecel_print(){
	$args = func_get_args();
	$text = implode(" ", $args);
	fwrite(STDOUT, $text."\n");
	return 0;
}


