<?php

define("FCPATH", dirname(dirname(__FILE__)));

include(FCPATH."/libpecel.php");

ob_start();

$files = glob(FCPATH."/tests/*.sql");
sort($files);

foreach ($files as $file) {

	$p = pecel_load_file($file);
	pecel_exec($p);

	$buffer = ob_get_contents();

	$name = basename($file, ".sql");
	$output = dirname($file)."/".$name.".out";
	$output_text = file_get_contents($output);

	if ($buffer === $output_text) {
		fwrite(STDOUT, "> {$name} OKAY\n");
	} else {
		fwrite(STDOUT, "> {$name} FAIL\n");
	}

	ob_clean();
}

