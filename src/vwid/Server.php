<?php
declare(strict_types=1);

use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\LoginInformation;
use robske_110\vwid\Main;

$startTime = microtime(true);

#ini_set('memory_limit','1024M');

const BASE_DIR = __DIR__."/../../";

require(BASE_DIR."src/Autoloader.php");

$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);

if(isset($config["timezone"])){
	ini_set("date.timezone", $config["timezone"]);
}

if(!isset($config["debug"])){
	$config["debug"] = [];
}
Logger::init(
	$config["debug"]["debug-enable"] ?? true, $config["debug"]["file-enable"] ?? true,
	($config["debug"]["debug-dir"] ?? BASE_DIR."/debug/")
);

function handleException(Throwable $t, $trace = null){
	if($trace === null){
		$trace = $t->getTrace();
	}
	ErrorUtils::logException($t, $trace);
	global $main;
	if($main === null){
		Logger::emergency("CRASHED WHILE STARTING; TRYING TO SHUTDOWN SAFELY");
	}
	forceShutdown();
}

set_exception_handler("handleException");

function handleSignal(int $signo){
	if($signo === SIGTERM or $signo === SIGINT or $signo === SIGHUP){
		shutdown();
	}
}

$signals = [SIGTERM, SIGINT, SIGHUP];
foreach($signals as $signal){
	pcntl_signal($signal, "handleSignal");
}

$doExit = false;
$main = new Main;
$tickCnter = 0;
Logger::log("Done. Startup took ".(microtime(true) - $startTime)."s");
$nextTick = 0;
while(!$doExit){
	if($nextTick > microtime(true)){
		time_sleep_until($nextTick);
	}
	
	$nextTick = microtime(true) + 1;
	$main->tick($tickCnter);
	pcntl_signal_dispatch();
	
	$tickCnter++;
}
forceShutdown();

function shutdown(){
	Logger::debug("Requested SHUTDOWN");
	global $doExit;
	$doExit = true;
}

function forceShutdown(){
	Logger::log("Shutting down...");
	global $main;
	if($main === null){
		Logger::critical("Forcibly shutting down while starting!");
	}else{
		$main->shutdown();
	}
	Logger::debug(">Closing Logger...");
	Logger::close();
	exit();
}