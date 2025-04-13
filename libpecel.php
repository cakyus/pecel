<?php

declare(strict_types=1);

const PECEL_SPACES = array(" ", "\n", "\r", "\t");

// end of line character
const PECEL_EOL = "\n";

class Pecel {

  // main function
  public PecelFunctionStatement $parent;

  // current database connection
  public $connection;

  public function load_file(string $file) :void {

    $stream = new PecelStream;
    $stream->stream = fopen($file, "r");

    $function = new PecelFunctionStatement;
    $function->name = "__main__";
    $function->parent = $function;
    $function->parse_body($stream);

    if ($stream->eof() == false) {
      $line = $stream->get_line();
      $line = var_export($line, true);
      fwrite(STDERR, strlen($line)." ".$line."\n");
      fwrite(STDERR, "ERROR parse incompleted\n");
      exit(1);
    }

    $this->parent = $function;
  }

  public function exec() {
    $this->parent->exec($this);
  }
}

/**
 * Stream of strings.
 **/

class PecelStream {

	public $stream;
  public int $line;

  public function __construct() {
    $this->line = 0;
  }

  /**
   * Create stream from string.
   **/

  public static function create($string) : PecelStream {
    $stream = new PecelStream;
    $stream->load($string);
    return $stream;
  }

  public function load(string $string) : void {
    $stream = fopen("php://memory", "r+");
    fwrite($stream, $string);
    rewind($stream);
    $this->stream = $stream;
  }

  public function seek(int $index) :int {
    return fseek($this->stream, $index);
  }

  public function seek_chars(array $seek_chars) :string {

    $chars = array();
    $char_count = 0;
    $nonspace_count = 0;

    while (true) {

      $char = fgetc($this->stream);

      if ($char === false) {
        break;
      }

      if ($char == "\n") {
        $this->line++;
      }

      if (in_array($char, $seek_chars)) {
        break;
      }

      if (  $nonspace_count == 0
        &&  in_array($char, PECEL_SPACES)
        ) {
        continue;
      }

      array_push($chars, $char);
      $char_count++;
      $nonspace_count++;
    }

    if ($char_count == 0) {
      return "";
    }

    // right trim

    for ($i = $char_count - 1; $i > -1; $i--) {
      $char = $chars[$i];
      if (in_array($char, PECEL_SPACES)) {
        unset($chars[$i]);
        continue;
      }
      break;
    }

    return implode("", $chars);
  }

  /**
   * Get non empty line.
   **/

  public function get_line() :string|null {

    // TODO support line continuation

    // You can use the line-continuation character, which is an underscore
    // (_), to break a long line of code over several lines in your file.
    // The underscore must be immediately preceded by a space and
    // immediately followed by a line terminator (carriage return).

    // https://learn.microsoft.com/en-us/dotnet/visual-basic/misc/bc30999

    while (true) {
      if ($this->eof()) {
        return null;
      }
      $line = $this->seek_chars(array(PECEL_EOL));
      if (strlen($line) == 0) {
        continue;
      }
      return $line;
    }
  }

  public function get_nonspace() :string|null {
    while (true) {
      if ($this->eof()) {
        return null;
      }
      $nonspace = $this->seek_chars(PECEL_SPACES);
      if (strlen($nonspace) == 0) {
        continue;
      }
      return $nonspace;
    }
  }

  public function tell() {
    return ftell($this->stream);
  }

  public function passthru() {
    fpassthru($this->stream);
  }

  /**
   * Test EOF (End Of File).
   **/

  public function eof() :bool {
    $index = ftell($this->stream);
    while (true) {
      $char = fgetc($this->stream);
      if ($char === false) {
        fseek($this->stream, $index);
        return true;
      }
      if (in_array($char, PECEL_SPACES)) {
        continue;
      }
      fseek($this->stream, $index);
      return false;
    }
  }

  public function close() {
    fclose($this->stream);
  }
}

class PecelElement {}

class PecelFunctionStatement implements PecelIStatement {

	public string $name;

	public array $variables  = array();
  public array $statements = array();

  // parent function
  public PecelFunctionStatement $parent;
  // current object methods , array of function statements
  public array $functions = array();

  // return value
  public string $return_type;
  public mixed $value;

  /**
   * sub FUNCTION_NAME(ARGUMENT..) RETURN_TYPE
   * end sub
   **/

  public function parse(PecelStream $stream, PecelFunctionStatement $parent) : bool {

    $index = $stream->tell();

    $keyword = $stream->get_nonspace();
    if ($keyword !== "sub") {
      $stream->seek($index);
      return false;
    }

    $function_name = $stream->seek_chars(array("("));
    if (is_null($function_name) == true) {
      $stream->seek($index);
      return false;
    }

    // check duplicate function name
    if (array_key_exists($function_name, $this->functions)) {
      throw new \Exception("TODO");
    }

    $parameter_text = $stream->seek_chars(array(")"));
    if (is_null($parameter_text) == true) {
      throw new \Exception("parameter_text is not defined");
    }

    $return_type = $stream->get_nonspace();
    if (is_null($return_type) == true) {
      throw new \Exception("return_type_text is not defined");
    }

    if (in_array($return_type, array("void", "bool", "int", "string"
      )) == false) {
      throw new \Exception("Invalid return type. '{$return_type}'");
    }

    $this->name = $function_name;
    $this->return_type = $return_type;
    $this->parent = $parent;
    $this->parse_body($stream);

    $line = $stream->get_line();
    if ($line !== "end sub") {
      throw new \Exception("Invalid keyword. '{$line}'");
    }

    return true;
  }

  public function parse_body(PecelStream $stream) : void {

    while (true) {

      if ($stream->eof() == true) {
        break;
      }

      $statement = new PecelCommentStatement;
      if ($statement->parse($stream, $this->parent)) {
        array_push($this->statements, $statement);
        continue;
      }

      $statement = new PecelSqlStatement;
      if ($statement->parse($stream, $this->parent)) {
        array_push($this->statements, $statement);
        continue;
      }

      // function declaration
      $statement = new PecelFunctionStatement;
      if ($statement->parse($stream, $this)) {
        $this->functions[$statement->name] = $statement;
        continue;
      }

      // function call
      $statement = new PecelMethodStatement;
      if ($statement->parse($stream, $this->parent)) {
        array_push($this->statements, $statement);
        continue;
      }

      break;
    }
  }

  public function exec(Pecel $pecel) : void {
    foreach ($this->statements as $statement) {
      $statement->exec($pecel);
    }
  }
}

interface PecelIStatement {
  public function parse(PecelStream $stream, PecelFunctionStatement $parent) : bool;
  public function exec(Pecel $pecel) : void;
}

/**
 * Function Call
 * print("Hello World")
 **/

class PecelMethodStatement implements PecelIStatement {

  public string $name;
  public array $arguments;

  public mixed $value;

  public function parse(PecelStream $stream, PecelFunctionStatement $parent) :bool {

    $index = $stream->tell();
    $function_name = $stream->get_nonspace();

    if (is_null($function_name) == true) {
      $stream->seek($index);
      return false;
    }

    if ($function_name == "end") {
      $stream->seek($index);
      return false;
    }

    $stream->seek($index);
    $argument_text = $stream->get_line();
    if (is_null($argument_text) == true) {
      throw new \Exception("argument_text is not defined");
    }

    if (strlen($function_name) == strlen($argument_text)) {
      $this->parent = $parent;
      $this->name = $function_name;
      $this->arguments = array();
      return true;
    }

    $index = strpos($argument_text, $function_name." ");
    if ($index === false) {
      throw new \Exception("Unknown error.");
    }

    $argument_text = substr(
        $argument_text
      , $index + strlen($function_name) + 1
      );

    $this->parent = $parent;
    $this->name = $function_name;
    $this->arguments = $this->parse_argument($argument_text);

    return true;
  }

  public function parse_argument(string $argument_text) :array {

    $arguments = array();
    if (strlen($argument_text) == 0) {
      return $arguments;
    }

    $chars = array();
    $char_count = strlen($argument_text);
    $status = "NONE";
    $text = "";

    for ($i = 0; $i < $char_count; $i++) {
      $char = substr($argument_text, $i, 1);
      array_push($chars, $char);
      if ($char == "'") {
        if ($status == "NONE") {
          $status = "STRING_BEGIN";
          continue;
        } elseif ($status == "STRING_BEGIN") {
          if ($chars[$i-1] == "\\") {
            $text .= $char;
            continue;
          }
          // STRING_END
          array_push($arguments, $text);
          $status = "NONE";
          $text = "";
          continue;
        }
        trigger_error("char ".var_export($char, true), E_USER_NOTICE);
        throw new \Exception("_");
      } elseif ($char == ",") {
        trigger_error("char ".var_export($char, true), E_USER_NOTICE);
        throw new \Exception("_");
      }
      if ($status == "STRING_BEGIN") {
        $text .= $char;
        continue;
      }
      trigger_error("char ".var_export($char, true), E_USER_NOTICE);
      trigger_error("argument_text ".var_export($argument_text, true), E_USER_NOTICE);
      trigger_error("this->name ".var_export($this->name, true), E_USER_NOTICE);
      throw new \Exception("_");
    }

    foreach ($arguments as $i => $value) {
      $type = gettype($value);
      if ($type == "string") {
        $object = new PecelString;
        $object->value = $value;
        $arguments[$i] = $object;
        continue;
      }
      throw new \Exception("_");
    }

    return $arguments;
  }

  public function exec(Pecel $pecel) :void {
    if ($this->name == "print") {
      call_user_func_array("pecel_print", $this->arguments);
    } elseif (array_key_exists($this->name, $this->parent->functions)) {
      // TODO handle arguments
      // TODO clone function for execution
      $function = $this->parent->functions[$this->name];
      $function->exec($pecel);
    } else {
      throw new \Exception("Undefined function. ".var_export($this->name, true));
    }
  }
}

class PecelSqlStatement implements PecelIStatement {

  public string $keyword;
  public string $text;

  public function parse(PecelStream $stream, PecelFunctionStatement $parent) :bool {

    $index = $stream->tell();
    $keyword = $stream->get_nonspace();
    $stream->seek($index);

    if (is_null($keyword) == true) {
      return false;
    }

    $keywords = array("select", "insert", "update", "delete", "with"
      , "create", "attach");
    if (in_array($keyword, $keywords) == false) {
      return false;
    }

    $text = $stream->seek_chars(array(";"));
    if (is_null($text) == true) {
      throw new \Exception("text is not defined.");
    }

    $this->text = $text;
    $this->keyword = $keyword;
    return true;
  }

  public function exec(Pecel $pecel) :void {

    if ($this->keyword == "select") {

      // print table

      $rows = array();
      $column_types = array();
      $column_sizes = array();
      $record_index = 0;
      $query = $pecel->connection->query($this->text);

      while ($record = $pecel->connection->fetch($query)){

        if ($record_index == 0) {
          $row = array();
          foreach ($record as $name => $value) {
            $column_types[$name] = gettype($value);
            $column_sizes[$name] = strlen($name);
            $row[$name] = $name;
          }
          array_push($rows, $row);
        }

        foreach ($record as $name => $value) {

          if ($column_types[$name] == "NULL") {
            $column_types[$name] = gettype($value);
          }

          if ($column_types[$name] == "string") {
            if (is_null($value) == true) {
              $value = "NULL";
            }
          } elseif ($column_types[$name] == "integer") {
            if (is_null($value) == true) {
              $value = "NULL";
            } else {
              $value = strval($value);
            }
          } elseif ($column_types[$name] == "NULL") {
            $value = "NULL";
          } else {
            throw new \Exception("Invalid column_type. ".$column_types[$name]);
          }

          if ($column_sizes[$name] < strlen($value)) {
            $column_sizes[$name] = strlen($value);
          }

          $record[$name] = $value;
        }

        array_push($rows, $record);
        $record_index++;
      }

      foreach ($rows as $row_index => $row) {
        $value_index = 0;
        foreach ($row as $name => $value) {
          if ($value_index > 0) {
            fwrite(STDERR, " ");
          }
          if ($row_index == 0) {
            $value = str_pad($value, $column_sizes[$name], " ", STR_PAD_RIGHT);
          } elseif ($column_types[$name] == "string") {
            $value = str_pad($value, $column_sizes[$name], " ", STR_PAD_RIGHT);
          } elseif ($column_types[$name] == "integer") {
            $value = str_pad($value, $column_sizes[$name], " ", STR_PAD_LEFT);
          }
          fwrite(STDERR, $value);
          $value_index++;
        }
        fwrite(STDERR, "\n");
      }
    } else {
      $pecel->connection->exec($this->text);
    }
  }
}

class PecelCommentStatement implements PecelIStatement {

  public function parse(PecelStream $stream, PecelFunctionStatement $parent) :bool {

    $index = $stream->tell();
    $line = $stream->get_line();

    if (is_null($line) == true) {
      $stream->seek($index);
      return false;
    }

    if (substr($line, 0, 3) != "-- ") {
      $stream->seek($index);
      return false;
    }

    return true;
  }

  public function exec(Pecel $pecel) : void {}
}

/**
 * Variable declaration.
 **/

class PecelVariable extends PecelElement {
	public string $name;
	public mixed $value;
}

class PecelValue {

  public static function create(string $string) :
    PecelString | PecelInteger {

    // "Hello World"
    if (  substr($string,  0, 1) == "'"
      &&  substr($string, -1, 1) == "'"
      &&  strlen($string) > 1
      ) {
      $value = new PecelString;
      $value->value = substr($string, 1, -1);
      return $value;
    }

    throw new \Exception("Invalid string. ".var_export($string, true));
  }

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

class PecelInteger {
	public int $value;
}

class PecelFloat {
	public float $value;
}

class PecelString {
	public string $value;
}

class PecelBoolean {
	public bool $value;
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

/***
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
****/

/**
 * SQLite database wrapper.
 **/

class PecelSqliteDatabase {

  protected $db;

  public function open($file) {
    $db = new \SQLite3($file);
    $this->db = $db;
  }

  public function exec($sql) {
    $result = $this->db->exec($sql);
    if ($result !== false){ return $result; }
    $text = $this->db->lastErrorMsg()."\n".$sql;
    throw new \Exception($text);
  }

  public function query($sql) {
    $query = @$this->db->query($sql);
    if ($query !== false) { return $query; }
    $text = $this->db->lastErrorMsg()."\n".$sql;
    throw new \Exception($text);
  }

  public function fetch($query) {
    return $query->fetchArray(\SQLITE3_ASSOC);
  }

  public function close() {
    return $this->db->close();
  }
}

/**
 * Create program from string
 **/

function pecel_load(string $string){

	$stream_stream = fopen("php://memory", "r+");
	fwrite($stream_stream, $string);
  rewind($stream_stream);

	$stream = new PecelStream;

	$stream->stream = $stream_stream;
	$stream->index  = 0;
	$stream->line   = 0;
	$stream->column = 0;

	return pecel_load_stream($stream);
}

function pecel_load_stream(PecelStream $stream) : PecelFunction {

	$function = new PecelFunction;
  $function->name = "__main__";
	$function->arguments = array();
	$function->variables = array();
  $function->parse($stream);

  return $function;
}

/**
 * Set next element.
 **/

function pecel_set_element(PecelElement $element, PecelStream $stream) :bool {

  if ($stream->eof() == true) {
    return false;
  }

	// > identify next element
	// > check elements conflict

	$element_type = null;

	if (pecel_is_comment($stream) == true) {
    if (is_null($element_type) == false) {
      throw new \Exception("Conflic '{$element_type}' with 'PecelComment'");
    }
		$element_type = "PecelComment";
	}

	// variable assignment

	if (pecel_is_assigment($stream) == true) {
    if (is_null($element_type) == false) {
      throw new \Exception("Conflic '{$element_type}' with 'PecelAssignment");
    }
		$element_type = "PecelAssignment";
	}

  trigger_error("_", E_USER_NOTICE); exit();

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

function pecel_seek(PecelStream $stream, int $index) {
	$stream->index = $index;
}

function pecel_read(PecelStream $stream, int $size) {
	$chars = fread($stream, $size);
	if ($chars !== false) {
		$stream->index = $stream->index + strlen($chars);
	}
	return $chars;
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

function pecel_is_comment(PecelStream $stream){
  if ($stream->substr(0,3) == "-- ") {
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

function pecel_is_assigment(PecelStream $stream) :bool {

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

function pecel_exec(PecelFunction $function){
  $function->exec();
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

function pecel_print() {

	$args = func_get_args();
	foreach ($args as $k => $v) {

		$type = gettype($v);

    $class = get_class($v);
    if ($class == "PecelString") {
      $v = $v->value;
    } elseif ($class == "PecelInteger") {
      $v = strval($v->value);
    } else {
      throw new \Exception("Class '{$class}' is invalid.");
    }

    $args[$k] = $v;
	}

  array_push($args, "\n");
	$text = implode(" ", $args);
	fwrite(STDOUT, $text);
}
