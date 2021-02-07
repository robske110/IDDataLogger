<?php
declare(strict_types=1);

use robske_110\vwid\LoginInformation;
use robske_110\vwid\MobileAppAPI;

const BASE_DIR = __DIR__."/../../";

require(BASE_DIR."src/Autoloader.php");

function debug($str){
	echo($str.PHP_EOL);
}

$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);

$id3Login = new MobileAppAPI(new LoginInformation($config["username"], $config["password"]));


$vehicles = json_decode($id3Login->getRequest("https://mobileapi.apps.emea.vwapps.io/vehicles", [], [
	"Authorization: Bearer ".$id3Login->getAppTokens()["accessToken"]
]), true)["data"];
var_dump($vehicles);

$vehicleToUse = $vehicles[0];
if(!empty($config["vin"])){
	foreach($vehicles as $vehicle){
		if($vehicle["vin"] == $config["vin"]){
			$vehicleToUse = $vehicle;
		}
	}
}
$vin = $vehicleToUse["vin"];
$name = $vehicleToUse["nickname"];

debug("getting cardata");
$data = json_decode($id3Login->getRequest("https://mobileapi.apps.emea.vwapps.io/vehicles/".$vin."/status", [], [
	"accept: */*",
	"content-type: application/json",
	"content-version: 1",
	"x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
	"user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
	"accept-language: de-de",
	"Authorization: Bearer ".$id3Login->getAppTokens()["accessToken"]
]), true);#

if(!empty($data["error"])){
	debug("err".print_r($data["errror"], true));
}

if(!isset($data["data"])){
	exit;
}

$data = $data["data"];

const WINDOW_HEATING_STATUS_DYN = 0;

$dataMapping = [
	"batteryStatus" => [
		"currentSOC_pct" => "batterySOC",
		"cruisingRangeElectric_km" => "remainingRange"
	],
	"chargingStatus" => [
		"remainingChargingTimeToComplete_min" => "remainingChargingTime",
		"chargingState" => "chargeState",
		"chargePower_kW" => "chargePower",
		"chargeRate_kmph" => "chargeRateKMPH"
	],
	"chargingSettings" => [
		"maxChargeCurrentAC" => null,
		"autoUnlockPlugWhenCharged" => "autoUnlockPlugWhenCharged",
		"targetSOC_pct" => "targetSOC"
	],
	"plugStatus" => [
		"plugConnectionState" => "plugConnectionState",
		"plugLockState" => "plugLockState"
	],
	"climatisationStatus" => [
		"remainingClimatisationTime_min" => "remainClimatisationTime",
		"climatisationState" => "hvacState"
	],
	"climatisationSettings" => [
		"targetTemperature_C" => "hvacTargetTemp",
		"climatisationWithoutExternalPower" => "hvacWithoutExternalPower",
		"climatizationAtUnlock" => "hvacAtUnlock",
		"windowHeatingEnabled" => null,
        "zoneFrontLeftEnabled" => null,
        "zoneFrontRightEnabled" => null,
		"zoneRearLeftEnabled" => null,
        "zoneRearRightEnabled" => null
	],
	"windowHeatingStatus" => [
		"windowHeatingStatus" => WINDOW_HEATING_STATUS_DYN
	]
];

$resultData = [];

var_dump($data);
readValues($data, $dataMapping, $resultData);
var_dump($resultData);

function readValues(array $data, array $dataMap, array &$resultData, ?string $lastLevelName = null){
	foreach($data as $key => $content){
		if (array_key_exists($key, $dataMap)){
			if (is_array($dataMap[$key])){
				readValues($content, $dataMap[$key], $resultData, $key);
			}else{
				if (!is_int($dataMap[$key])){
					$resultData[$dataMap[$key] ?? $key] = $content;
				}else{
					switch ($dataMap[$key]){
						case WINDOW_HEATING_STATUS_DYN:
							foreach ($content as $window){
								$resultData[$window["windowLocation"] . "WindowHeatingState"] = $window["windowHeatingState"];
							}
							break;
						default:
							debug("Unable to read content at " . $key . ": " . print_r($content, true));
					}
				}
			}
		}elseif($lastLevelName !== "" && $key == "carCapturedTimestamp"){
			$resultData[$lastLevelName."Timestamp"] = new DateTime($content);
		}else{
			debug("Ignored content at ".$key.": "/*.print_r($content, true)*/);
		}
	}
}
