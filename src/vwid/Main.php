<?php
declare(strict_types=1);

namespace robske_110\vwid;

use DateTime;
use DateTimeZone;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\webutils\CurlError;

class Main{
	/** @var resource */
	private $db;
	
	private bool $firstTick = true;
	
	public array $config;
	
	private MobileAppLogin $idLogin;
	private string $vin;
	
	private array $carStatusData;
	private array $lastWrittenCarStatus = [];
	
	const DB_FIELDS = [
		"batterySOC",
		"remainingRange",
		"remainingChargingTime",
		"chargeState",
		"chargePower",
		"chargeRateKMPH",
		"maxChargeCurrentAC",
		"autoUnlockPlugWhenCharged",
		"targetSOC",
		"plugConnectionState",
		"plugLockState",
		"remainClimatisationTime",
		"hvacState",
		"hvacTargetTemp",
		"hvacWithoutExternalPower",
		"hvacAtUnlock",
		"windowHeatingEnabled",
		"zoneFrontLeftEnabled",
		"zoneFrontRightEnabled",
		"zoneRearLeftEnabled",
		"zoneRearRightEnabled",
		"frontWindowHeatingState",
		"rearWindowHeatingState"
	];
	
	const DATA_MAPPING = [
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
			"autoUnlockPlugWhenCharged" => null,
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
			"windowHeatingStatus" => self::WINDOW_HEATING_STATUS_DYN
		]
	];
	
	const WINDOW_HEATING_STATUS_DYN = 0;
	
	public function __construct(){
		Logger::log("Reading config...");
		$this->config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);
		
		Logger::log("Connecting to db...");
		$this->db = pg_connect("host=".$this->config["db"]["host"]." dbname=".$this->config["db"]["dbname"]." user=".$this->config["db"]["user"]);
		if($this->db !== false){
			$query = "INSERT INTO carStatus(time";
			foreach(self::DB_FIELDS as $dbField){
				$query .= ", ".$dbField;
			}
			$query .= ") VALUES(";
			for($i = 1; $i <= count(self::DB_FIELDS); ++$i){
				$query .= "$".$i.", ";
			}
			$query .= "$".($i).") ON CONFLICT (time) DO UPDATE SET ";
			foreach(self::DB_FIELDS as $dbField){
				$query .= $dbField." = excluded.".$dbField.", ";
			}
			$query = substr($query, 0, strlen($query)-2);
			$query .= ";";
			Logger::debug("Preparing query ".$query."...");
			if(pg_prepare(
					$this->db,
					"carStatus_write",
					$query
				) === false){
				throw new \Exception("Failed to prepare the prepared statement: ".pg_last_error($this->db));
			}
		}else{
			throw new \Exception("Failed to connect to db!");
		}
		
		Logger::log("Logging in...");
		$loginInformation = new LoginInformation($this->config["username"], $this->config["password"]);
		$this->idLogin = new MobileAppLogin($loginInformation);
		$this->login();
	}
	
	public function tick(int $tickCnter){
		if(false){
			return;
		}elseif($this->firstTick === true){
			Logger::log("Ready!");
			$this->firstTick = false;
		}
		if($tickCnter % 60 == 0){
			$this->fetchCarStatus();
			$this->writeCarStatus();
		}
	}
	
	private function login(){
		$this->idLogin->login();
		
		$vehicles = json_decode($this->idLogin->getRequest("https://mobileapi.apps.emea.vwapps.io/vehicles", [], [
			"Authorization: Bearer ".$this->idLogin->getAppTokens()["accessToken"]
		]), true)["data"];
		var_dump($vehicles);
		
		$vehicleToUse = $vehicles[0];
		if(!empty($this->config["vin"])){
			foreach($vehicles as $vehicle){
				if($vehicle["vin"] == $this->config["vin"]){
					$vehicleToUse = $vehicle;
				}
			}
		}
		$this->vin = $vehicleToUse["vin"];
		$name = $vehicleToUse["nickname"];
	}
	
	private function writeCarStatus(){
		$data = [];
		$dateTime = null;
		foreach(self::DATA_MAPPING as $key => $val){
			if($dateTime === null){
				$dateTime = $this->carStatusData[$key."Timestamp"];
			}else{
				if($this->carStatusData[$key."Timestamp"]->getTimestamp() > $dateTime->getTimestamp()){
					$dateTime = $this->carStatusData[$key."Timestamp"];
				}
			}
		}
		$data[] = $dateTime->format('Y\-m\-d\TH\:i\:s');
		foreach(self::DB_FIELDS as $dbField){
			if(is_bool($this->carStatusData[$dbField])){
				$data[] = $this->carStatusData[$dbField] ? "true" : "false";
				continue;
			}
			$data[] = $this->carStatusData[$dbField];
		}
		if($data === $this->lastWrittenCarStatus){
			return;
		}
		Logger::log("Writing new data for timestamp ".$data[0]);
		#var_dump($data);
		
		$res = pg_execute($this->db, "carStatus_write", $data);
		if($res === false){
			Logger::critical("Could not write to db!");
			return;
		}
		$this->lastWrittenCarStatus = $data;
	}
	
	private function fetchCarStatus(){
		Logger::debug("fetching car status...");
		try{
			$data = $this->idLogin->getRequest("https://mobileapi.apps.emea.vwapps.io/vehicles/".$this->vin."/status", [], [
				"accept: */*",
				"content-type: application/json",
				"content-version: 1",
				"x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
				"user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
				"accept-language: de-de",
				"Authorization: Bearer ".$this->idLogin->getAppTokens()["accessToken"]
			]);
		}catch(CurlError $curlError){
			Logger::critical("Failed to fetch car status");
			ErrorUtils::logException($curlError);
			return;
		}
		try{
			$data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException $jsonException){
			if(str_contains($data, "Unauthorized") || str_contains($data, "401")){
				$this->login();
			}else{
				Logger::critical("Error while decoding car status.");
				throw $jsonException;
			}
		}
		
		if(!empty($data["error"])){
			Logger::critical("Error while fetching car status: ".print_r($data["error"], true));
			return;
		}
		
		if(!isset($data["data"])){
			Logger::critical("Failed to get carStatus: ".print_r($data, true));
			var_dump($data);
			return;
		}
		
		$data = $data["data"];
		
		$carStatusData = [];
		
		#var_dump($data);
		$this->readValues($data, self::DATA_MAPPING, $carStatusData);
		$this->carStatusData = $carStatusData;
		#var_dump($carStatusData);
	}
	
	private function readValues(array $data, array $dataMap, array &$resultData, ?string $lastLevelName = null){
		foreach($data as $key => $content){
			if (array_key_exists($key, $dataMap)){
				if (is_array($dataMap[$key])){
					$this->readValues($content, $dataMap[$key], $resultData, $key);
				}else{
					if (!is_int($dataMap[$key])){
						$resultData[$dataMap[$key] ?? $key] = $content;
					}else{
						switch ($dataMap[$key]){
							case self::WINDOW_HEATING_STATUS_DYN:
								foreach ($content as $window){
									$resultData[$window["windowLocation"] . "WindowHeatingState"] = $window["windowHeatingState"];
								}
								break;
							default:
								Logger::notice("Unable to read content at ".$key.": ".print_r($content, true));
						}
					}
				}
			}elseif($lastLevelName !== "" && $key == "carCapturedTimestamp"){
				$resultData[$lastLevelName."Timestamp"] = new DateTime($content);
			}else{
				Logger::debug("Ignored content at ".$key.": "/*.print_r($content, true)*/);
			}
		}
	}
	
	public function shutdown(){
		Logger::debug(">Closing DataBase connection...");
		pg_close($this->db);
	}
}