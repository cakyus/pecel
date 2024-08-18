<?php

define("FCPATH", dirname(dirname(__FILE__)));

include(FCPATH."/libpecel.php");

$files = glob(FCPATH."/tests/*.sql");
sort($files);
foreach ($files as $file) {
	echo("> ".basename($file)."\n");
	$p = pecel_load_file($file);
	pecel_exec($p);
}

