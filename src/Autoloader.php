<?php
declare(strict_types=1);
spl_autoload_register(function ($class){
	if(class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)){
		return false;
	}
	
	$dirNamespace = str_replace("\\", DIRECTORY_SEPARATOR, substr($class, strpos($class, "\\")));
	include __DIR__.DIRECTORY_SEPARATOR.$dirNamespace.".php";
	echo("Loading ".basename($dirNamespace)."...\n");
	return true;
});