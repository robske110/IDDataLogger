<?php
declare(strict_types=1);

namespace robske_110\vwid\chargesession;

use robske_110\utils\Logger;
use robske_110\vwid\DatabaseConnection;

class ChargeSessionHandler{
	private DatabaseConnection $db;
	private ChargeSession $chargeSession;
	
	public function __construct(DatabaseConnection $db){
		$this->db = $db;
		
		// prepare insert query
		
		$res = $db->query("SELECT startTime, endTime FROM chargingSessions WHERE endTime != NULL ORDER BY startTime DESC LIMIT 1");
		
		if(empty($res)){
			Logger::notice("Building past charge sessions...");
			$this->buildAll();
		}
	}
	
	public function processCarStatus(array $carStatus){
		if($this->chargeSession === null && $carStatus["plugconnectionstate"] == "connected"){
			Logger::notice("Plugged car in at ".$carStatus["time"]);
			$chargeSession = new ChargeSession();
		}
		Logger::debug($carStatus["time"].":".$entry["chargestate"]);
		if($this->chargeSession !== null){
			if($this->chargeSession->processEntry($carStatus)){
				$chargeSession->niceOut();
				$this->writeChargeSession();
				$this->chargeSession = null;
			}
		}
	}
	
	public function buildAll(){
		$res = $this->db->query("SELECT time, batterysoc, remainingrange, chargestate, chargepower, chargeratekmph, targetsoc, plugconnectionstate FROM carStatus ORDER BY time ASC");
		
		foreach($res as $entry){
			$this->processCarStatus($entry);
		}
	}
	
	private function writeChargeSession(){
		// insert query
	}
}