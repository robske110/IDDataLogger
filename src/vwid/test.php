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

$res = $db->queryStatement("SELECT time, batterysoc, remainingrange, chargestate, chargepower, chargeratekmph, targetsoc, plugconnectionstate FROM carStatus ORDER BY time ASC")->fetchAll(PDO::FETCH_ASSOC);

$chargeSession = null;
foreach($res as $entry){
	if($chargeSession === null && $entry["plugconnectionstate"] == "connected"){
		Logger::notice("Plugged car in at ".$entry["time"]);
		$chargeSession = new ChargeSession();
	}
	Logger::debug($entry["time"].":".$entry["chargestate"]);
	if($chargeSession !== null){
		if($chargeSession->processEntry($entry)){
			$chargeSession->niceOut();
			$chargeSession = null;
		}
	}
}