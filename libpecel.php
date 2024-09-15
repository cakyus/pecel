<?php

declare(strict_types=1);

/**
 * Stream of strings.
 **/

class PecelStream {

	public $stream;

	public int $index;
	// @var int $size
	// Last known size of the stream.
	public int $size;

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
	public string $type;
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
	public PecelVariable $variable;
	public PecelValue $value;
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
	// variable name
	// Examples:
	//  - "a"
	//  - "a1"
	//  - "a1_2"
	//  - "a1_b"
	const VARIABLE     = '([a-z]([a-z0-9_]*[a-z0-9])*)';
	const TYPE         = '(int|float|string|bool|array|cursor)';
	const SYMBOL       = '([\(\)\[\]\,=])';
	const ROUND_OPEN   = '(\()';
	const ROUND_CLOSE  = "(\))";
	const SQUARE_OPEN  = '(\[)';
	const SQUARE_CLOSE = '(\])';
}

function pecel_load_file(string $file){

	$stream = new PecelStream;

	$stream->stream = fopen($file, "r");
	$stream->index  = 0;
	$stream->size   = 0;
	$stream->line   = 0;
	$stream->column = 0;

	return pecel_load_stream($stream);
}

/**
 * Create program from string
 **/

function pecel_load(string $string){

	$stream = new PecelStream;

	$stream->stream = fopen("data://text/plain,{$string}", "r");
	$stream->index  = 0;
	$stream->size   = 0;
	$stream->line   = 0;
	$stream->column = 0;

	return pecel_load_stream($stream);
}

function pecel_load_stream(PecelStream $stream){

	$function = new PecelProgram;
	$function->arguments = array();
	$function->variables = array();

	$element = new PecelElement;
	$element->owner_function = $function;

	$function->element = $element;

	pecel_set_element($element, $stream);

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
		$string = substr($text->value, $text->position, 10);
		$string = str_replace(array("\n"), " ", $string);
		$string = trim($string);
		throw new \Exception("Parse Error. '{$string}' is invalid."
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

function pecel_seek(PecelText $text, int $position) {
	$text->position = $position;
}

function pecel_match(string $pattern, PecelText $text) : PecelMatch | bool {

	$space  = PecelPattern::SPACE;
	$string = substr($text->value, $text->position);

	if (preg_match("/^{$pattern}{$space}*/", $string, $m) == false) {
		return false;
	}

	$match = new PecelMatch;
	$match->value = $m[1];
	$match->text  = $m[0];

	$text->position = $text->position + strlen($match->text);

	return $match;

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
 * - '123.45' => string
 * - true     => bool
 * - false    => bool
 **/

function pecel_get_value(PecelText $text) : PecelValue | bool {

	$string = substr($text->value, $text->position);

	// first character
	$char = substr($text->value, $text->position, 1);
	$numbers = "0123456789";

	$type = "unknown";

	if ($char == "'") {
		$type = "string";
	} elseif (strpos($numbers, $char) !== false) {
		// begin with number => integer or float
		$type = "number";
	} elseif (substr($text->value, $text->position, 4) == "true") {
		$type = "bool";
	} elseif (substr($text->value, $text->position, 5) == "false") {
		$type = "bool";
	}

	if ($type == "unknown") {
		return false;
	}

	// string

	if ($type == "string") {

		$length  = strlen($string);
		$value   = "";
		$value_text = "'";
		$has_eof = false;

		for ($i = 1; $i < $length; $i++) {

			$char = substr($string, $i, 1);
			if ($char != "'") {
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
			if ($char_j != "'") {
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

		// zap remaining spaces
		pecel_match(PecelPattern::SPACE, $text);

		return $object;
	}

	if ($type == "number") {

		// 1234
		// 1234.56
		// 1234.567.90

		$position = $text->position;
		$value = "";
		$dot_count = false;

		while (true) {

			$char = substr($text->value, $position, 1);

			if ($char === false) {
				// eof
				break;
			} elseif ($char == "."){
				if ($dot_count == 0) {
					$dot_count = 1;
				} else {
					break;
				}
			} elseif (strpos($numbers, $char) === false){
				break;
			}

			$value .= $char;
			$position++;
		}

		$object = new PecelValue;

		if ($dot_count == 0) {
			$object->type = "int";
			$object->value = intval($value);
		} else {
			$object->type = "float";
			$object->value = floatval($value);
		}

		$object->text = $value;
		$text->position = $text->position + strlen($value);

		// zap remaining spaces
		pecel_match(PecelPattern::SPACE, $text);

		return $object;
	}

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
 * Get chars until next LF or EOF
 **/

function pecel_get_line(PecelText $text) : PecelMatch | bool {

	$match = new PecelMatch;

	$match->value = "";
	$match->text  = "";

	for ($i = $text->position; $i < $text->length; $i++) {
		$char = substr($text->value, $i, 1);
		$match->text .= $char;
		if ($char == "\n") {
			break;
		}
		$match->value .= $char;
	}

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

function pecel_is_comment(PecelText $text){
	if (substr($text->value, $text->position, 3) == "-- ") {
		return true;
	}
	return false;
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

function pecel_is_assigment(PecelText $text) : bool {

	$position = $text->position;

	$match = pecel_match(PecelPattern::VARIABLE, $text);
	if ($match == false) {
		pecel_seek($text, $position);
		return false;
	}

	$match = pecel_get_symbol($text);
	if ($match == false) {
		pecel_seek($text, $position);
		return false;
	}

	if ($match->value != "=") {
		pecel_seek($text, $position);
		return false;
	}

	pecel_seek($text, $position);
	return true;
}

/**
 * Variable declaration.
 **/

function pecel_set_variable(PecelElement $element, PecelText $text){

	// var i int

	// "var"
	$match = pecel_get_word($text);

	// "i"
	$match = pecel_match(PecelPattern::VARIABLE, $text);
	if ($match == false) {
		throw new \Exception("Invalid variable name.");
	}
	$name = $match->value;

	// "int"
	$match = pecel_match(PecelPattern::TYPE, $text);
	if ($match == false) {
		throw new \Exception("Invalid variable name.");
	}
	$type = $match->value;

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

	$variable->name = $name;
	$variable->type = $type;
	$variable->owner_function = $element->owner_function;

	// check duplicated variable declaration
	foreach ($element->owner_function->variables as $function_variable) {
		if ($function_variable->name == $variable->name) {
			throw new \Exception("'{$name}' already defined in"
				." line {$function_variable->line}"
				." column {$function_variable->column}"
				);
		}
	}

	array_push($variable->owner_function->variables, $variable);

	$element->has_next_element = true;
	$element->next_element = $variable;
}

// - begin with "-- "
// - end with LF or EOF

function pecel_set_comment(PecelElement $element, PecelText $text){

	$comment = new PecelComment;
	$comment->owner_function = $element->owner_function;

	$match = pecel_get_line($text);

	$element->has_next_element = true;
	$element->next_element = $comment;

	$text->position += strlen($match->text);
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

		$element->next_element = $function;
		$element->has_next_element = true;

		return true;
	}

	while (true) {

		// print a value
		// print('Hello')

		$value = pecel_get_value($text);
		if ($value) {
			array_push($function->arguments, $value);
		} else {

			// print a variable
			// print(i)

			$match = pecel_match(PecelPattern::VARIABLE, $text);
			$variable_name = $match->value;
			$variable_exists = false;

			// variable_name must already declared in owner_function
			foreach ($element->owner_function->variables as $variable) {
				if ($variable->name == $variable_name) {
					$variable_exists = true;
					break;
				}
			}

			if ($variable_exists == false) {
				throw new \Exception("Variable is undefined.");
			}

			array_push($function->arguments, $variable);
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

	$element->next_element = $function;
	$element->has_next_element = true;
}

function pecel_set_assignment(PecelElement $element, PecelText $text){

	$match = pecel_match(PecelPattern::VARIABLE, $text);

	$variable_name = $match->value;
	$variable_type = "undefined";

	// > variable_name must already declared in owner_function
	foreach ($element->owner_function->variables as $variable) {
		if ($variable->name == $variable_name) {
			$variable_type = $variable->type;
			break;
		}
	}

	if ($variable_type == "undefined") {
		throw new \Exception("Undefined variable '{$variable_name}'");
	}

	// s = 'Hello World !'
	$match = pecel_get_symbol($text);

	$value = pecel_get_value($text);

	// > check types
	if ($variable_type != $value->type) {
		throw new \Exception("Type '{$variable_type}' can not assign to '{$value->type}'");
	}

	$assignment = new PecelAssignment;
	$assignment->owner_function = $element->owner_function;
	$assignment->variable = $variable;
	$assignment->value = $value;

	$element->next_element = $assignment;
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
	} elseif ($element_type == "PecelFloat") {
		// do nothing
	} elseif ($element_type == "PecelString") {
		// do nothing
	} elseif ($element_type == "PecelBoolean") {
		// do nothing
	} elseif ($element_type == "PecelAssignment") {
		pecel_exec_assigment($element);
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

function pecel_exec_assigment(PecelAssignment $assignment){

	// check types
	if ( $assignment->variable->type == $assignment->value->type){
		$assignment->variable->value = $assignment->value->value;
	} else {
		throw new \Exception("Invalid");
	}
}

function pecel_print(){

	$args = func_get_args();
	foreach ($args as $k => $v) {

		$type = gettype($v);

		if ($type == "object"){
			$class = get_class($v);
			if ($class == "PecelValue") {
				$v = $v->value;
			} elseif ($class == "PecelInteger") {
				$v = strval($v->value);
			} else {
				throw new \Exception("Class '{$class}' is invalid.");
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
