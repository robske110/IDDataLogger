<?php
define("ALLOW_KEY_AUTHENTICATION", true);
require "login/loginCheck.php";
require "chargingSessionDataProvider.php";

if(isset($_GET['beginTime']) && !is_numeric($_GET['beginTime'])){
	exit;
}

if(isset($_GET['endTime']) && !is_numeric($_GET['endTime'])){
	exit;
}

$chargingSessions = (new chargingSessionDataProvider((int) ($_GET['beginTime'] ?? 0), (int) ($_GET['endTime'] ?? time())))->getChargingSessions();

$chgSessions = [];
foreach($chargingSessions as $chargingSession){
	$chgSessions[] = $chargingSession->toArray();
}

echo(json_encode($chgSessions));