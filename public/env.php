<?php
const ENV_FILE = __DIR__."/../../.env";
if(file_exists(ENV_FILE)){
	$envvars = parse_ini_file(ENV_FILE, false, INI_SCANNER_TYPED);
	foreach($envvars as $key => $var){
		$_ENV[$key] = $var;
	}
}

$required_envvars = ["DB_HOST", "DB_NAME", "DB_USER"];
foreach($required_envvars as $required_envvar){
	if(!isset($_ENV[$required_envvar])){
		echo("Error: required envvar ".$required_envvar." not set!\n");
		exit;
	}
}

if(isset($_ENV["TIMEZONE_OVERRIDE"])){
	date_default_timezone_set($_ENV["TIMEZONE_OVERRIDE"]);
}