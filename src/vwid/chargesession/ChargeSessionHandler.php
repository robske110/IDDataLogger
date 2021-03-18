<?php
declare(strict_types=1);

namespace robske_110\vwid\chargesession;

use PDOStatement;
use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\db\DatabaseConnection;

class ChargeSessionHandler{
	private DatabaseConnection $db;
	private ?ChargeSession $chargeSession = null;
	
	private PDOStatement $chargeSessionWrite;
	const DB_FIELDS = [
		"startTime",
		"endTime",
		"chargeStartTime",
		"chargeEndTime",
		"duration",
		"avgChargePower",
		"maxChargePower",
		"minChargePower",
		"chargeEnergy",
		"rangeStart",
		"rangeEnd",
		"targetSOC",
		"socStart",
        "socEnd"
	];
	
	public function __construct(DatabaseConnection $db){
		$this->db = $db;
		
		$query = QueryCreationHelper::createInsert("chargingSessions", self::DB_FIELDS);
		Logger::debug("Preparing query ".$query."...");
		$this->chargeSessionWrite = $db->prepare($query);
		
		$res = $db->query("SELECT startTime, endTime FROM chargingSessions WHERE endTime IS NOT NULL ORDER BY startTime DESC LIMIT 1");
		
		if(empty($res)){
			Logger::notice("Building past charge sessions...");
			$this->buildAll();
		}
	}
	
	public function processCarStatus(array $carStatus, bool $alwaysWrite = true){
		foreach($carStatus as $key => $value){
			if(strtolower($key) !== $key){
				$carStatus[strtolower($key)] = $value;
				unset($carStatus[$key]);
			}
		}
		
		if($this->chargeSession === null && $carStatus["plugconnectionstate"] == "connected"){
			Logger::notice("Plugged car in at ".$carStatus["time"]);
			$this->chargeSession = new ChargeSession();
		}
		Logger::debug($carStatus["time"].":".$carStatus["chargestate"]);
		if($this->chargeSession !== null){
			if($this->chargeSession->processEntry($carStatus)){
				$this->chargeSession->niceOut();
				$this->writeChargeSession();
				$this->chargeSession = null;
			}elseif($alwaysWrite){
				$this->writeChargeSession();
			}
		}
	}
	
	public function buildAll(){
		$res = $this->db->query("SELECT time, batterysoc, remainingrange, chargestate, chargepower, chargeratekmph, targetsoc, plugconnectionstate FROM carStatus ORDER BY time ASC");
		
		foreach($res as $entry){
			$this->processCarStatus($entry, false);
		}
		
		if($this->chargeSession !== null){
			Logger::debug("Continuing charging session!");
		}
	}
	
	private function writeChargeSession(){
		$this->chargeSessionWrite->execute([
			$this->chargeSession->startTime->format('Y\-m\-d\TH\:i\:s'),
			$this->chargeSession?->endTime->format('Y\-m\-d\TH\:i\:s'),
			$this->chargeSession?->chargeStartTime->format('Y\-m\-d\TH\:i\:s'),
			$this->chargeSession?->chargeEndTime->format('Y\-m\-d\TH\:i\:s'),
			$this->chargeSession->chargeDuration,
			$this->chargeSession->avgChargePower,
			$this->chargeSession->maxChargePower,
			$this->chargeSession->minChargePower,
			$this->chargeSession->integralChargeEnergy,
			$this->chargeSession->rangeStart,
			$this->chargeSession->rangeEnd,
			$this->chargeSession->targetSOC,
			$this->chargeSession->socStart,
			$this->chargeSession->socEnd
		]);
	}
}