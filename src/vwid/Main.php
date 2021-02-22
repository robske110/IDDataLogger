<?php
declare(strict_types=1);

namespace robske_110\vwid;

use DateTime;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDAPIException;
use robske_110\vwid\api\exception\IDAuthorizationException;
use robske_110\vwid\api\LoginInformation;
use robske_110\vwid\api\MobileAppAPI;
use robske_110\webutils\CurlError;

class Main{
	private bool $firstTick = true;
	
	public array $config;
	
	private DatabaseConnection $db;
	
	private MobileAppAPI $idAPI;
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
	
	private int $currentUpdateRate = 60;
	
	public function __construct(){
		Logger::log("Reading config...");
		$this->config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);
		Logger::addOutputFilter($this->config["password"]);
		
		Logger::log("Connecting to db...");
		$this->db = new DatabaseConnection(
			$this->config["db"]["host"], $this->config["db"]["dbname"], $this->config["db"]["user"], $this->config["db"]["password"] ?? null);
		$this->initQuery();
		
		new CarPictureHandler($this);
		
		Logger::log("Logging in...");
		$loginInformation = new LoginInformation($this->config["username"], $this->config["password"]);
		$this->idAPI = new MobileAppAPI($loginInformation);
		$this->login();
	}
	
	public function getDB(){
		return $this->db;
	}
	
	public function initQuery(){
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
				$this->db->getConnection(),
				"carStatus_write",
				$query
			) === false){
			throw new \Exception("Failed to prepare the carStatus_write statement: ".pg_last_error($this->db));
		}
	}
	
	public function tick(int $tickCnter){
		if($this->firstTick === true){
			Logger::log("Ready!");
			$this->firstTick = false;
		}
		if($tickCnter % $this->currentUpdateRate == 0){
			if(!$this->fetchCarStatus()){
				return;
			}
			//increase update rate while charging or hvac active:
			if($this->carStatusData["chargeState"] == "readyForCharging" && $this->carStatusData["hvacState"] == "off"){
				$this->currentUpdateRate = 60*5;
			}else{
				$this->currentUpdateRate = 60;
			}
			$this->writeCarStatus();
		}
	}
	
	private function login(){
		$this->idAPI->login();
		
		$vehicles = $this->idAPI->apiGet("vehicles")["data"];
		
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
		
		$res = pg_execute($this->db->getConnection(), "carStatus_write", $data);
		if($res === false){
			Logger::critical("Could not write to db!");
			$this->db->connect();
			return;
		}
		$this->lastWrittenCarStatus = $data;
	}
	
	/**
	 * Fetches the new car status from the vehicles/vin/status endpoint
	 *
	 * @return bool Whether the fetching was successful.
	 */
	private function fetchCarStatus(): bool{
		Logger::log("Fetching car status...");
		try{
			$data = $this->idAPI->apiGet("vehicles/".$this->vin."/status");
		}catch(IDAuthorizationException $exception){
			Logger::notice("IDAuthorizationException: ".$exception->getMessage());
			Logger::notice("Refreshing tokens...");
			if(!$this->idAPI->refreshToken()){
				Logger::notice("Failed to refresh token, trying to re-login");
				$this->login();
			}else{
				Logger::log("Successfully refreshed token");
			}
			$this->currentUpdateRate = 1; //trigger update on next tick
			return false;
		}catch(IDAPIException $idAPIException){
			Logger::critical("IDAPIException while trying to fetch car status");
			ErrorUtils::logException($idAPIException);
			return false;
		}catch(CurlError $curlError){
			Logger::critical("CurlError while trying to fetch car status");
			ErrorUtils::logException($curlError);
			return false;
		}
		
		if(!empty($data["error"])){
			Logger::critical("Error while fetching car status: ".print_r($data["error"], true));
			return false;
		}
		
		if(!isset($data["data"])){
			Logger::critical("Failed to get carStatus: No Data in response!");
			Logger::var_dump($data, "decoded Data");
			return false;
		}
		
		$data = $data["data"];
		
		$carStatusData = [];
		
		#var_dump($data);
		$this->readValues($data, self::DATA_MAPPING, $carStatusData);
		$this->carStatusData = $carStatusData;
		#var_dump($carStatusData);
		return true;
	}
	
	private function readValues(array $data, array $dataMap, array &$resultData, ?string $lastLevelName = null){
		foreach($data as $key => $content){
			if(array_key_exists($key, $dataMap)){
				if(is_array($dataMap[$key])){
					$this->readValues($content, $dataMap[$key], $resultData, $key);
				}else{
					if(!is_int($dataMap[$key])){
						$resultData[$dataMap[$key] ?? $key] = $content;
					}else{
						switch($dataMap[$key]){
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
				Logger::debug("Ignored content at ".$key/*.": ".print_r($content, true)*/);
			}
		}
	}
	
	public function shutdown(){
		Logger::debug(">Closing DataBase connection...");
		pg_close($this->db);
	}
}