<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\Logger;

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
				$this->main->getDB()->getConnection(),
				"carStatus_write",
				$query
			) === false){
			throw new \Exception("Failed to prepare the carStatus_write statement: ".pg_last_error($this->db));
		}
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
			if($dateTime === null){
				$dateTime = $carStatusData[$key."Timestamp"];
			}else{
				if($carStatusData[$key."Timestamp"]->getTimestamp() > $dateTime->getTimestamp()){
					$dateTime = $carStatusData[$key."Timestamp"];
				}
			}
		}
		$data[] = $dateTime->format('Y\-m\-d\TH\:i\:s');
		foreach(self::DB_FIELDS as $dbField){
			if(is_bool($carStatusData[$dbField])){
				$data[] = $carStatusData[$dbField] ? "true" : "false";
				continue;
			}
			$data[] = $carStatusData[$dbField];
		}
		if($data === $this->lastWrittenCarStatus){
			return true;
		}
		Logger::log("Writing new data for timestamp ".$data[0]);
		#var_dump($data);
		
		$res = pg_execute($this->main->getDB()->getConnection(), "carStatus_write", $data);
		if($res === false){
			Logger::critical("Could not write to db!");
			$this->main->getDB()->connect();
			return false;
		}
		$this->lastWrittenCarStatus = $data;
		return true;
	}
}