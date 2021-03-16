<?php
declare(strict_types=1);

namespace robske_110\vwid\chargesession;

use robske_110\vwid\DatabaseConnection;

class ChargeSessionHandler{
	private DatabaseConnection $db;
	
	public function __construct(DatabaseConnection $db){
		$this->db = $db;
		
		$db->query("SELECT startTime, endTime FROM chargingSessions WHERE endTime != NULL ORDER BY startTime DESC LIMIT 1");
		
		$res = $db->query("SELECT time, batterysoc, remainingrange, chargestate, chargepower, chargeratekmph, targetsoc, plugconnectionstate FROM carStatus ORDER BY time ASC");
		
		$chargeSession = null;
		foreach($res as $entry){
			if($chargeSession === null && $entry["plugconnectionstate"] == "connected"){
				Logger::notice("Plugged car in at ".$entry["time"]);
				$chargeSession = new ChargeSession();
			}
			Logger::debug($entry["time"].":".$entry["chargestate"]);
			if($chargeSession !== null){
				if($chargeSession->processEntry($entry)){
					$chargeSession->niceOut();
					$chargeSession = null;
				}
			}
		}
		
	}
}