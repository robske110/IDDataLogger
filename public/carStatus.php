<?php
define("ALLOW_KEY_AUTHENTICATION", true);
require "login/loginCheck.php";
require_once "DatabaseConnection.php";

$attemptRefresh = ($_GET['attemptRefresh'] ?? false) == "true";

$statusAt = null;
if(isset($_GET['at'])){
	$statusAt = " WHERE time <= '".(new DateTime($_GET['at'], new DateTimeZone("UTC")))->format("Y-m-d\TH:i:s")."'";
}

if($attemptRefresh){
	if($statusAt !== null){
		error("unsupported argument combination!");
	}
	echo("not implemented");
}

$columns = "time, batterySOC, remainingRange, remainingChargingTime, chargeState, chargePower, chargeRateKMPH, targetSOC, plugConnectionState, plugLockState, remainClimatisationTime, hvacState, hvacTargetTemp";
$sqlCmd = "SELECT ".$columns." FROM carStatus".($statusAt ?? "")." ORDER BY time DESC LIMIT 1";

$sqlChargeStart = "WITH chargeState_wprev AS (
  SELECT time, chargeState, lag(chargeState) over(ORDER BY time ASC) AS prev_chargeState
  FROM carStatus".($statusAt ?? "")."
  ORDER BY time DESC
)
SELECT time, chargeState, prev_chargeState
FROM chargeState_wprev
WHERE prev_chargeState IN ('readyForCharging', 'notReadyForCharging') AND chargeState = 'charging' LIMIT 1";

if(($_ENV["DB_DRIVER"] ?? "pgsql") != "pgsql"){
	if($statusAt !== null){
		$statusAt = "WHERE startTime ".substr($statusAt, 11);
	}
	$sqlChargeStart = "SELECT startTime AS time FROM chargingSessions ".($statusAt ?? "")." ORDER BY startTime DESC LIMIT 1;";
}


$chargeStartRes = DatabaseConnection::getInstance()->queryStatement($sqlChargeStart)->fetch(PDO::FETCH_ASSOC);

$carStatus = DatabaseConnection::getInstance()->queryStatement($sqlCmd)->fetch(PDO::FETCH_ASSOC);
if(empty($carStatus)){
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
$carStatus["lastChargeStartTime"] = (new DateTime($chargeStartRes["time"] ?? $carStatus["time"], new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM);
$carStatus["time"] = (new DateTime($carStatus["time"], new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM);
#var_dump($carStatus);

echo(json_encode($carStatus));

function error(string $msg){
	echo(json_encode(["error" => $msg]));
	exit;
}