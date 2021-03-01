<?php
declare(strict_types=1);

namespace robske_110\vwid;

use http\Exception\RuntimeException;
use PDOException;
use PDOStatement;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;

class CarStatusWriter{
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
	
	private Main $main;
	
	private PDOStatement $carStatusWrite;
	
	private array $lastWrittenCarStatus = [];
	
	public function __construct(Main $main){
		$this->main = $main;
		$this->initQuery();
	}
	
	private function initQuery(){
		$query = "INSERT INTO carStatus(time";
		foreach(self::DB_FIELDS as $dbField){
			$query .= ", ".$dbField;
		}
		$query .= ") VALUES(";
		for($i = 1; $i <= count(self::DB_FIELDS); ++$i){
			$query .= "?, ";
		}
		$query .= "?) ";
		$query .= QueryCreationHelper::createUpsert($this->main->getDB()->getDriver(), "time", self::DB_FIELDS);
		Logger::debug("Preparing query ".$query."...");
		$this->carStatusWrite = $this->main->getDB()->prepare($query);
	}
	
	/**
	 * @param array $carStatusData The carStatusData to write to the db
	 *
	 * @return bool whether writing was successful (also returns true on skipping)
	 */
	public function writeCarStatus(array $carStatusData): bool{
		$data = [];
		$dateTime = null;
		foreach(CarStatusFetcher::DATA_MAPPING as $key => $val){
			if(!isset($carStatusData[$key."Timestamp"])){
				Logger::notice("Could not find expected key '".$key."Timestamp' in carStatusData. Unexpected changes in older or newer car software can cause this!");
				continue;
			}
			if($dateTime === null){
				$dateTime = $carStatusData[$key."Timestamp"];
			}else{
				if($carStatusData[$key."Timestamp"]->getTimestamp() > $dateTime->getTimestamp()){
					$dateTime = $carStatusData[$key."Timestamp"];
				}
			}
		}
		if($dateTime == null){
			Logger::var_dump($carStatusData, "carStatusData");
			throw new RuntimeException("Data does not contain any timestamps, unable to write to db!");
		}
		$data[] = $dateTime->format('Y\-m\-d\TH\:i\:s');
		foreach(self::DB_FIELDS as $dbField){
			if(!isset($carStatusData[$dbField])){
				Logger::notice("Could not find expected key '".$dbField."' in carStatusData. Unexpected changes in older or newer car software can cause this!");
				$data[] = null;
				continue;
			}
			if(is_bool($carStatusData[$dbField])){
				$data[] = $carStatusData[$dbField] ? "1" : "0";
				continue;
			}
			$data[] = $carStatusData[$dbField];
		}
		if($data === $this->lastWrittenCarStatus){
			return true;
		}
		Logger::log("Writing new data for timestamp ".$data[0]);
		#var_dump($data);
		
		try{
			$this->carStatusWrite->execute($data);
		}catch(PDOException $e){
			ErrorUtils::logException($e);
			Logger::critical("Could not write to db, attempting reconnect...");
			$this->main->getDB()->connect();
			$this->initQuery();
			return false;
		}
		$this->lastWrittenCarStatus = $data;
		return true;
	}
}