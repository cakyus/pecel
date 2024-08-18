<?php

define("FCPATH", dirname(dirname(__FILE__)));

include(FCPATH."/libpecel.php");

ob_start();

$files = glob(FCPATH."/tests/*.sql");
sort($files);

foreach ($files as $file) {

	$name = basename($file, ".sql");
	fwrite(STDOUT, "> {$name} ");

	$pecel = pecel_load_file($file);

// 	if ($name == "02-comment"){
// 		ob_end_flush();
// 		var_dump($pecel);
// 		exit();
// 	}

	pecel_exec($pecel);

	$buffer = ob_get_contents();

	$output = dirname($file)."/".$name.".txt";
	if (is_file($output) == true) {
		$output_text = file_get_contents($output);
	} else {
		$output_text = "";
	}

	if ($buffer === $output_text) {
		fwrite(STDOUT, "OKAY\n");
	} else {
		fwrite(STDOUT, "FAIL\n");
		fwrite(STDOUT, "expect {$output_text}\n");
		fwrite(STDOUT, "result {$buffer}\n");
		exit(1);
	}

	ob_clean();
}

