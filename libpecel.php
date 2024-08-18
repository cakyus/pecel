<?php

declare(strict_types=1);

class PecelProgram {
}

/**
 * Create program from string
 **/

function pecel_load($text){
	$pecel = new PecelProgram;
	// function argument..
	// print 'Hello World'
	if (preg_match("/^print\s+\'([^\']+)\'/", $text, $match)) {
		echo($match[1]."\n");
	}
}

function pecel_load_file($file){
	$text = file_get_contents($file);
	return pecel_load($text);
}

/**
 * Execute program
 **/

function pecel_exec($pecel){

}
