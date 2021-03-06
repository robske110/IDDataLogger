<?php
declare(strict_types=1);

use robske_110\utils\Logger;
use robske_110\vwid\chargesession\ChargeSession;
use robske_110\vwid\DatabaseConnection;

const BASE_DIR = __DIR__."/../../";

require(BASE_DIR . "src/Autoloader.php");

function debug($str){
	echo($str.PHP_EOL);
}

Logger::init(false, false);

$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);

$db = new DatabaseConnection(
	$config["db"]["host"], $config["db"]["dbname"],
	$config["db"]["user"], $config["db"]["password"] ?? null,
	$config["db"]["driver"] ?? "pgsql"
);

$res = $db->queryStatement("SELECT time, batterysoc, remainingrange, chargestate, chargepower, chargeratekmph, targetsoc, plugconnectionstate, pluglockstate FROM carStatus ORDER BY time ASC")->fetchAll(PDO::FETCH_ASSOC);

$lastChargeState = null;
$inPluggedInSession = false;
$chargeSession = null;
foreach($res as $entry){
	if(!$inPluggedInSession && $entry["plugconnectionstate"] == "connected"){
		Logger::notice("Plugged car in at ".$entry["time"]);
		$inPluggedInSession = true;
	}
	if($inPluggedInSession){
		Logger::debug($entry["time"].":".$entry["chargestate"]);
		if($entry["chargestate"] == "charging" && $chargeSession === null){
			Logger::log("Started charging session at ".$entry["time"]);
			$chargeSession = new ChargeSession(new DateTime($entry["time"]));
		}
		if($chargeSession !== null){
			$chargeSession->processEntry($entry);
		}
		if($entry["chargestate"] == "readyForCharging" && $lastChargeState != "readyForCharging"){
			Logger::log("Ended session at ".$entry["time"]);
			Logger::debug("lCS".$lastChargeState." cs:".$entry["chargestate"]);
			
			if($chargeSession !== null){
				$chargeSession->setEndTime(new DateTime($entry["time"]));
				$chargeSession->niceOut();
			}
			$chargeSession = null;
		}
	}
	if($inPluggedInSession && $entry["plugconnectionstate"] == "disconnected"){
		Logger::notice("Unplugged car at ".$entry["time"]);
		$inPluggedInSession = false;
	}
	$lastChargeState = $entry["chargestate"];
}