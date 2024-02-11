<?php
declare(strict_types=1);

namespace robske_110\vwid;

use DateTime;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\API;
use robske_110\vwid\api\exception\IDAPIException;
use robske_110\vwid\api\exception\IDAuthorizationException;
use robske_110\vwid\api\LoginInformation;
use robske_110\vwid\api\MobileAppAPI;
use robske_110\webutils\CurlError;

class CarStatusFetcher{
	/** @var CarStatusUpdateReceiver[]  */
	private array $updateReceivers;
	private array $config;
	
	private MobileAppAPI $idAPI;
	private string $vin;
	
	private array $carStatusData;
	
	private int $currentUpdateRate = 1;
	
	const WINDOW_HEATING_STATUS_DYN = 0;
	
	const DATA_MAPPING = [
		"charging" => [
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
			]
		],
		"climatisation" => [
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
		],
		"measurements" => [
			"odometerStatus" => [
				"odometer" => null
			]
		]
	];

	const JOBS = [
		"access",
		"activeVentilation",
		"auxiliaryHeating",
		"batteryChargingCare",
		"batterySupport",
		"charging",
		"chargingProfiles",
		"climatisation",
		"climatisationTimers",
		"departureProfiles",
		"fuelStatus",
		"honkAndFlash",
		"hybridCarAuxiliaryHeating",
		"userCapabilities",
		"vehicleHealthWarnings",
		"vehicleHealthInspection",
		"vehicleLights",
		"measurements",
		"departureTimers",
		"lvBattery",
		"readiness"
	];
	
	public function __construct(array $config){
		$this->config = $config;
		
		Logger::log("Logging in...");
		$loginInformation = new LoginInformation($this->config["username"], $this->config["password"]);
		$this->idAPI = new MobileAppAPI($loginInformation);
		$this->login();
	}
	
	public function registerUpdateReceiver(CarStatusUpdateReceiver $updateReceiver){
		$this->updateReceivers[] = $updateReceiver;
	}
	
	public function tick(int $tickCnter){
		if($tickCnter % $this->currentUpdateRate == 0){
			if(!$this->fetchCarStatus()){
				return;
			}
			//increase update rate while charging or hvac active or when last update was less than 6 minutes ago
			$timestamp = CarStatusWriter::getCarStatusTimestamp($this->carStatusData); //TODO: Refactor?
			if(
				(
					$this->carStatusData["chargeState"] == "notReadyForCharging" ||
					$this->carStatusData["chargeState"] == "readyForCharging" //pre 3.0
				) &&
				$this->carStatusData["hvacState"] == "off" &&
				(time() - $timestamp?->getTimestamp()) > 60 * 6
			){
				$this->currentUpdateRate = $this->config["base-updaterate"] ?? 60 * 10;
			}else{
				$this->currentUpdateRate = $this->config["increased-updaterate"] ?? 60;
			}
			foreach($this->updateReceivers as $updateReceiver){
				$updateReceiver->carStatusUpdate($this->carStatusData);
			}
		}
	}
	
	private function login(){
		$this->idAPI->login();
		
		$vehicles = $this->idAPI->apiGet("vehicles")["data"];
		
		$vehicleToUse = $vehicles[0];
		if(!empty($this->config["vin"])){
			foreach($vehicles as $vehicle){
				if($vehicle["vin"] === $this->config["vin"]){
					$vehicleToUse = $vehicle;
				}
			}
			if($vehicleToUse["vin"] !== $this->config["vin"]){
				Logger::var_dump($vehicles, "vehicles");
				Logger::warning(
					"Could not find the vehicle with the specified vin ('".$this->config["vin"]
					."')! If fetching fails, please double check your vin!"
				);
			}
		}
		$this->vin = $vehicleToUse["vin"];
		//$name = $vehicleToUse["nickname"];
	}
	
	/**
	 * Fetches the new car status from the vehicles/vin/status endpoint
	 *
	 * @return bool Whether the fetching was successful.
	 */
	private function fetchCarStatus(): bool{
		Logger::log("Fetching car status...");
		try{
			$data = $this->idAPI->apiGet("vehicles/".$this->vin."/selectivestatus?jobs=".implode(",", self::JOBS));
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
		
		if(($error = $data["error"] ?? null) !== null || ($error = $data["userCapabilities"]["capabilitiesStatus"]["error"] ?? null) !== null){
			Logger::var_dump($data, "decoded Data");
			Logger::warning("VW API reported error while fetching car status: ".print_r($error, true));
			Logger::notice("Ignoring these errors and continuing to attempt to decode data...");
		}
		
		$carStatusData = [];
		
		if(API::$VERBOSE){
			Logger::var_dump($data);
		}
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
			}elseif($key == "value"){ //filter "value" key
				$this->readValues($content, $dataMap, $resultData, $lastLevelName); //skip over value key level
			}elseif($lastLevelName !== "" && $key == "carCapturedTimestamp"){
				$resultData[$lastLevelName."Timestamp"] = new DateTime($content);
			}else{
				Logger::debug("Ignored content at ".$key/*.": ".print_r($content, true)*/);
			}
		}
	}
	
	public function getCarStatusData(): array{
		return $this->carStatusData;
	}
}