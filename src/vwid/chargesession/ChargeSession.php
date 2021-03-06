<?php
declare(strict_types=1);

namespace robske_110\vwid\chargesession;

use DateTime;
use robske_110\utils\Logger;

class ChargeSession{
	public int $duration; //sec
	public float $avgChargePower;
	public float $maxChargePower = 0;
	public float $minChargePower = PHP_INT_MAX;
	public float $integralChargeEnergy = 0; //kWs
	public int $rangeStart;
	public int $rangeEnd;
	public int $targetSOC;
	public int $socStart;
	public int $socEnd;
	
	public DateTime $startTime;
	public DateTime $endTime;
	
	public function __construct(DateTime $chargeStartTime){
		$this->startTime = $chargeStartTime;
	}
	
	public function setEndTime(DateTime $endTime){
		$this->endTime = $endTime;
		$this->duration = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
	}
	
	private int $entryCount = 0;
	private float $chargePowerAccum = 0;
	private float $lastChargePower;
	private int $lastTime;
	
	public function processEntry(array $entry){
		++$this->entryCount;
		if(!isset($this->rangeStart)){
			$this->rangeStart = (int) $entry["remainingrange"];
		}
		$this->rangeEnd = (int) $entry["remainingrange"];
		if(!isset($this->socStart)){
			$this->socStart = (int) $entry["batterysoc"];
		}
		$this->socEnd = (int) $entry["batterysoc"];
		$this->targetSOC = (int) $entry["targetsoc"];
		
		$currTime = (new DateTime($entry["time"]))->getTimestamp();
		if(isset($this->lastTime)){
			$this->integralChargeEnergy += ($currTime - $this->lastTime) * $this->lastChargePower;
		}
		$this->lastTime = $currTime;
		$this->lastChargePower = (float) $entry["chargepower"];
		
		if($entry["chargepower"] == 0){
			Logger::critical($entry["time"].":".$entry["chargepower"]."kW");
			return;
		}
		$this->maxChargePower = max($this->maxChargePower, (float) $entry["chargepower"]);
		$this->minChargePower = min($this->minChargePower, (float) $entry["chargepower"]);
		$this->chargePowerAccum += $entry["chargepower"];
		$this->chargeKMRaccum += $entry["chargeratekmph"];
		
		$this->avgChargePower = $this->chargePowerAccum / $this->entryCount;
		$this->avgChargeKMR = $this->chargeKMRaccum / $this->entryCount;
	}
	
	private float $chargeKMRaccum = 0;
	public float $avgChargeKMR;
	public float $maxChargeKMR = 0;
	public float $minChargeKMR = PHP_INT_MAX;
	
	public function niceOut(){
		Logger::log("range: start: ".$this->rangeStart."km end: ".$this->rangeEnd."km duration: ".round($this->duration/3600, 1)."h".PHP_EOL.
			"SOC: start: ".$this->socStart."% end: ".$this->socEnd."% target: ".$this->targetSOC."% chargeEnergy:".round($this->integralChargeEnergy / 3600, 2)."kWh cE_soc_calc".(58*($this->socEnd-$this->socStart))."kWh*100".PHP_EOL.
			"POWER: avg: ".round($this->avgChargePower, 1)."kW / ".round($this->avgChargeKMR, 1)." min: ".$this->minChargePower."kW max: ".$this->maxChargePower."kW");
		Logger::log("avgChgKMR/avgChgPower:".round($this->avgChargePower/$this->avgChargeKMR, 2)." start".round($this->rangeStart/$this->socStart, 1)." end".round($this->rangeEnd/$this->socEnd, 1));
	}
}