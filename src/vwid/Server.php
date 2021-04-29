<?php
declare(strict_types=1);

use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\Main;

$startTime = microtime(true);

const MIN_PHP_VERSION = "8.0.0";
if(version_compare(MIN_PHP_VERSION, PHP_VERSION) > 0){
	echo("At least PHP ".MIN_PHP_VERSION." is required. Your installed version is ".PHP_VERSION."!");
	exit(1);
}
if(php_sapi_name() !== "cli"){
	echo("This script is to be run in cli!");
	exit(1);
}
$extensions = ["curl", "json", "gd", "dom", "pdo", "pcntl", ["pdo_pgsql", "pdo_mysql"]];

$missingExtensions = "";
foreach($extensions as $ext){
	if(is_array($ext)){
		$ok = false;
		foreach($ext as $possibleExt){
			$ok = $ok ? true : extension_loaded($possibleExt);
		}
		if(!$ok){
			$missingExtensions .= "At least one of the following php extensions is required: ".implode(", ", $ext);
		}
		continue;
	}
	if(!extension_loaded($ext)){
		$missingExtensions .= "The php extension ".$ext." is required!".PHP_EOL;
	}
}
if(!empty($missingExtensions)){
	echo($missingExtensions);
	exit(1);
}

#ini_set('memory_limit','1024M');

const BASE_DIR = __DIR__."/../../";

require(BASE_DIR."src/Autoloader.php");

if(($_SERVER['argv'][1] ?? "") == "--configwizard"){
	new \robske_110\vwid\wizard\ConfigWizard();
	exit;
}

$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);

if(isset($config["timezone"])){
	ini_set("date.timezone", $config["timezone"]);
}

if(!isset($config["debug"])){
	$config["debug"] = [];
}
Logger::init(
	$config["logging"]["debug-enable"] ?? true, $config["logging"]["file-enable"] ?? true,
	($config["logging"]["log-dir"] ?? BASE_DIR."/log/")
);

const VERSION = "v0.0.6";
const IS_RELEASE = false;

$hash = exec("git -C \"".BASE_DIR."\" rev-parse HEAD 2>/dev/null");
$exitCode = -1;
exec("git -C \"".BASE_DIR."\" diff --quiet 2>/dev/null", $out, $exitCode);
if($exitCode == 1){
	$hash .= "-dirty";
}
Logger::log("Starting ID DataLogger Version ".VERSION.(IS_RELEASE ? "" : "-InDev").(!empty($hash) ? " (".$hash.")" : "")."...");

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