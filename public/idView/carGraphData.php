<?php
require "../login/loginCheck.php";
require "carGraphDataProvider.php";

if(!isset($_GET['beginTime']) || !is_numeric($beginTime = $_GET['beginTime'])){
	exit;
}

if(isset($_GET['endTime']) && !is_numeric($_GET['endTime'])){
	exit;
}

$dataBracketing = ($_GET['dataBracketing'] ?? false) == "true";

echo(json_encode((new carGraphDataProvider((int) $beginTime, (int) ($_GET['endTime'] ?? time()), $dataBracketing))->getGraphData()->toArray()));