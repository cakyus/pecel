<?php

exit( main() );

function main(){

	if ($_SERVER["argc"] != 2) {
		echo "File is required.\n";
		return 1;
	}

	$file = $_SERVER["argv"][1];

	if (is_file($file) == false) {
		echo "File '{$file}' is not found.\n";
		return 1;
	}

	include("libpecel.php");

	$pecel = pecel_load_file($file);
	pecel_exec($pecel);

	return 0;
}

