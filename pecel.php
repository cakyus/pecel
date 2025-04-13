<?php

/**
 * Usage: php pecel [OPTIONS] FILE [DB]
 **/

function main(){

  $commands = array();
  $options = array();

  for ($i = 1; $i < $_SERVER["argc"]; $i++) {
    $argument = $_SERVER["argv"][$i];
    // command
    if (substr($argument, 0, 1) != "-") {
      array_push($commands, $argument);
      continue;
    }
    // TODO option
    //   "-name", "-name=value"
  }

  if (isset($commands[0]) == false) {
    fwrite(STDERR, "ERROR ".__LINE__." FILE is required\n");
    exit(1);
  }

  $db = null;
  if (isset($commands[1]) == true) {
    $db = $commands[1];
  }

  $file = $commands[0];

	include("libpecel.php");

  $pecel = new Pecel;
  $pecel->load_file($file);
  if (is_null($db) == false) {
    $database = new PecelSqliteDatabase;
    $database->open($db);
    $pecel->connection = $database;
  }
  $pecel->exec();

	return 0;
}

main();

