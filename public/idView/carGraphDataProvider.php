<?php
declare(strict_types=1);
require_once __DIR__."/../DatabaseConnection.php";

class carGraphData{
	public array $time = [];
	public array $batterySOC = [];
	public array $targetSOC = [];
	public array $remainingRange = [];
	public array $remainingChargingTime = [];
	public array $chargePower = [];
	public array $chargeRateKMPH = [];
	
	public function toArray(): array{
		return [
			"time" => $this->time,
			"batterySOC" => $this->batterySOC,
			"targetSOC" => $this->targetSOC,
			"remainingRange" => $this->remainingRange,
			"remainingChargingTime" => $this->remainingChargingTime,
			"chargePower" => $this->chargePower,
			"chargeRateKMPH" => $this->chargeRateKMPH
		];
	}
}

class carGraphDataProvider{
	private int $beginTime;
	private int $endTime;
	private bool $dataBracketing;
	
	private float $took;
	
	public function __construct(int $beginTime, int $endTime, bool $dataBracketing){
		$this->beginTime = $beginTime;
		$this->endTime = $endTime;
		$this->dataBracketing = $dataBracketing;
	}
	
	public function getGraphData(): ?carGraphData{
		$beginTime = microtime(true);
		$data = $this->fetchFromDB($this->beginTime, $this->endTime);
		
		$carGraphData = new carGraphData;
		if(empty($data)){
			return $carGraphData;
		}
		
		$carGraphData->batterySOC = array_column($data, "batterysoc");
		$carGraphData->targetSOC = array_column($data, "targetsoc");
		$carGraphData->remainingRange = array_column($data, "remainingrange");
		$carGraphData->remainingChargingTime = array_column($data, "remainingchargingtime");
		$carGraphData->chargePower = array_column($data, "chargepower");
		$carGraphData->chargeRateKMPH = array_column($data, "chargeratekmph");

		$carGraphData->time = array_column($data, "time");
		$timeCnt = count($carGraphData->time);
		for($i = 0; $i < $timeCnt; $i++){
			$carGraphData->time[$i] = ((new DateTime($carGraphData->time[$i], new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM));
		}
		
		if($this->dataBracketing){
			$this->performDataBracketing($carGraphData);
		}
		$endTime = microtime(true);
		$this->took = $endTime - $beginTime;
		
		return $carGraphData;
	}
	
	public function getTook(): float{
		return $this->took;
	}
	
	const MAX_DATA_POINT_DISTANCE = 2*60;
	const MAX_GROUP_TIME_LEN = 5*60;
	
	private function performDataBracketing(carGraphData $data){
		//step 1: build groups
		$groups = [];
		$dataCnt = count($data->time);
		for($i = 0; $i < $dataCnt; $i++){
			#out("Begining group at $i");
			$groupStartTime = (new DateTime($data->time[$i]))->getTimestamp();
			$groups[$groupStartTime] = [$i];
			$groupStart = $i;
			$lastTime = PHP_INT_MAX;
			for($groupPos = $i+1; $groupPos < $dataCnt; ++$groupPos){
				$currTime = (new DateTime($data->time[$groupPos]))->getTimestamp();
				if($currTime - $groupStartTime >= self::MAX_GROUP_TIME_LEN || $currTime - $lastTime >= self::MAX_DATA_POINT_DISTANCE){
					#out("Exiting group at $groupPos: ".($currTime - $groupStartTime)."s from groupStart and ".($currTime - $lastTime)."s from last point in group. (start: $time[$i] end: $time[$groupPos])");
					break;
				}
				$groups[$groupStartTime][] = $groupPos;
				$lastTime = $currTime;
			}
			$i = $groupPos-1;
		}
		//step 2: only use last value of each group
		foreach($groups as $group){
			for($i = 0; $i < count($group)-1; ++$i){
				#var_dump($group);
				#echo("rem at $group[$i] ::".count($group));
				unset($data->time[$group[$i]]);
				unset($data->batterySOC[$group[$i]]);
				unset($data->targetSOC[$group[$i]]);
				unset($data->remainingRange[$group[$i]]);
				unset($data->remainingChargingTime[$group[$i]]);
				unset($data->chargePower[$group[$i]]);
				unset($data->chargeRateKMPH[$group[$i]]);
			}
		}
		$data->time = array_values($data->time);
		$data->batterySOC = array_values($data->batterySOC);
		$data->targetSOC = array_values($data->targetSOC);
		$data->remainingRange = array_values($data->remainingRange);
		$data->remainingChargingTime = array_values($data->remainingChargingTime);
		$data->chargePower = array_values($data->chargePower);
		$data->chargeRateKMPH = array_values($data->chargeRateKMPH);
	}
	
	private function fetchFromDB(int $beginTime, int $endTime): array{
		$beginTime = new DateTime("@".$beginTime, new DateTimeZone("UTC"));
		$endTime = new DateTime("@".$endTime, new DateTimeZone("UTC"));
		$data = DatabaseConnection::getInstance()->query(
			"SELECT time, batterysoc, targetsoc, remainingrange, remainingchargingtime, chargepower, chargeratekmph FROM carStatus WHERE time >= TIMESTAMP '".
			$beginTime->format("Y-m-d\TH:i:s").
			"' AND time <= TIMESTAMP '".$endTime->format("Y-m-d\TH:i:s")."' ORDER BY time ASC"
		);
		#var_dump($data);
		return $data;
	}
}