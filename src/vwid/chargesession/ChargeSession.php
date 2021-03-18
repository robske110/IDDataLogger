<?php
declare(strict_types=1);

namespace robske_110\vwid\chargesession;

use DateTime;
use robske_110\utils\Logger;

class ChargeSession{
	public int $chargeDuration = 0; //sec
	public float $avgChargePower = 0;
	public float $maxChargePower = 0;
	public float $minChargePower = PHP_INT_MAX;
	public float $integralChargeEnergy = 0; //kWs
	public int $rangeStart;
	public int $rangeEnd;
	public int $targetSOC;
	public int $socStart;
	public int $socEnd;
	
	public ?DateTime $chargeStartTime = null;
	public ?DateTime $chargeEndTime = null;
	public DateTime $startTime;
	public ?DateTime $endTime = null;
	
	private function setChargeEndTime(DateTime $endTime){
		$this->chargeEndTime = $endTime;
		$this->chargeDuration = $this->chargeEndTime->getTimestamp() - $this->chargeStartTime->getTimestamp();
	}
	
	private int $entryCount = 0;
	private float $chargePowerAccum = 0;
	private float $lastChargePower;
	private int $lastTime;
	private string $lastChargeState = "";
	
	/**
	 * @param array $entry
	 *
	 * @return bool Whether this charge session is finished
	 */
	public function processEntry(array $entry): bool{
		if(!isset($this->startTime)){
			$this->startTime = new DateTime($entry["time"]);
		}
		$this->rangeEnd = (int) $entry["remainingrange"];
		$this->socEnd = (int) $entry["batterysoc"];
		$this->targetSOC = (int) $entry["targetsoc"];
		
		if($this->chargeStartTime === null){
			if($entry["chargestate"] == "charging"){
				Logger::log("Started charging session at ".$entry["time"]);
				$this->chargeStartTime = new DateTime($entry["time"]);
			}else{
				return false;
			}
		}
		if($entry["chargestate"] == "readyForCharging" && $this->lastChargeState != "readyForCharging"){
			Logger::log("Ended session at ".$entry["time"]);
			Logger::debug("lCS".$this->lastChargeState." cs:".$entry["chargestate"]);
			$this->setChargeEndTime(new DateTime($entry["time"]));
		}
		
		if($entry["plugconnectionstate"] == "disconnected"){
			Logger::notice("Unplugged car at ".$entry["time"]);
			$this->endTime = new DateTime($entry["time"]);
		}
		
		if($this->endTime !== null){
			return true;
		}
		
		++$this->entryCount;
		if(!isset($this->rangeStart)){
			$this->rangeStart = (int) $entry["remainingrange"];
		}
		
		if(!isset($this->socStart)){
			$this->socStart = (int) $entry["batterysoc"];
		}
		
		$currTime = (new DateTime($entry["time"]))->getTimestamp();
		if(isset($this->lastTime)){
			$this->integralChargeEnergy += ($currTime - $this->lastTime) * $this->lastChargePower;
		}
		$this->lastTime = $currTime;
		$this->lastChargePower = (float) $entry["chargepower"];
		
		if($entry["chargepower"] == 0){
			Logger::critical($entry["time"].":".$entry["chargepower"]."kW");
			return false;
		}
		$this->maxChargePower = max($this->maxChargePower, (float) $entry["chargepower"]);
		$this->minChargePower = min($this->minChargePower, (float) $entry["chargepower"]);
		$this->chargePowerAccum += $entry["chargepower"];
		$this->chargeKMRaccum += $entry["chargeratekmph"];
		
		$this->avgChargePower = $this->chargePowerAccum / $this->entryCount;
		$this->avgChargeKMR = $this->chargeKMRaccum / $this->entryCount;
		return false;
	}
	
	private float $chargeKMRaccum = 0;
	public float $avgChargeKMR;
	public float $maxChargeKMR = 0;
	public float $minChargeKMR = PHP_INT_MAX;
	
	public function niceOut(){
		Logger::log("range: start: ".$this->rangeStart."km end: ".$this->rangeEnd."km duration: ".round($this->chargeDuration/3600, 1)."h".PHP_EOL.
			"SOC: start: ".$this->socStart."% end: ".$this->socEnd."% target: ".$this->targetSOC."% chargeEnergy:".round($this->integralChargeEnergy / 3600, 2)."kWh cE_soc_calc".(58*($this->socEnd-$this->socStart))."kWh*100".PHP_EOL.
			"POWER: avg: ".round($this->avgChargePower, 1)."kW / ".round($this->avgChargeKMR, 1)." min: ".$this->minChargePower."kW max: ".$this->maxChargePower."kW");
		Logger::log("avgChgKMR/avgChgPower:".round($this->avgChargePower/$this->avgChargeKMR, 2)." start".round($this->rangeStart/$this->socStart, 1)." end".round($this->rangeEnd/$this->socEnd, 1));
	}
}