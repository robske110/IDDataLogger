<?php
define("ALLOW_KEY_AUTHENTICATION", true);
require "login/loginCheck.php";

$attemptRefresh = ($_GET['attemptRefresh'] ?? false) == "true";

$statusAt = null;
if(isset($_GET['at'])){
	$statusAt = " WHERE time <= '".(new DateTime($_GET['at'], new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM)."'";
}

if($attemptRefresh){
	if($statusAt !== null){
		error("unsupported argument combination!");
	}
	echo("not implemented");
}

$inst = pg_connect("host=".$_ENV["DB_HOST"]." dbname=".$_ENV["DB_NAME"]." user=".$_ENV["DB_USER"].(isset($_ENV["DB_PASSWORD"]) ? " password=".$_ENV["DB_PASSWORD"] : ""));
$columns = "time, batterySOC, remainingRange, remainingChargingTime, chargeState, chargePower, chargeRateKMPH, targetSOC, plugConnectionState, plugLockState, remainClimatisationTime, hvacState, hvacTargetTemp";
$sqlCmd = "SELECT ".$columns." FROM carStatus".($statusAt ?? "")." ORDER BY time DESC LIMIT 1";

$carStatusRes = pg_query($inst, $sqlCmd);

$sqlChargeStart = "WITH chargeState_wprev AS (
  SELECT time, chargeState, lag(chargeState) over(ORDER BY time ASC) AS prev_chargeState
  FROM carStatus".($statusAt ?? "")."
  ORDER BY time DESC
)
SELECT time, chargeState, prev_chargeState
FROM chargeState_wprev
WHERE prev_chargeState = 'readyForCharging' AND chargeState = 'charging' LIMIT 1";

$chargeStartRes = pg_query($inst, $sqlChargeStart);

$carStatus = pg_fetch_assoc($carStatusRes);
if($carStatus == false){
	error("no data");
}
$columns = explode(", ", $columns);
foreach($carStatus as $key => $value){
	foreach($columns as $columnName){
		if($key === strtolower($columnName) && $key !== $columnName){
			$carStatus[$columnName] = $value;
			unset($carStatus[$key]);
			break;
		}
	}
}
$carStatus["time"] = (new DateTime($carStatus["time"]))->format(DateTimeInterface::ATOM);
$carStatus["lastChargeStartTime"] = (new DateTime(pg_fetch_assoc($chargeStartRes)["time"]))->format(DateTimeInterface::ATOM);
#var_dump($carStatus);

echo(json_encode($carStatus));

function error(string $msg){
	echo(json_encode(["error" => $msg]));
	exit;
}