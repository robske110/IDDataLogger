<?php
declare(strict_types=1);
require_once "DatabaseConnection.php";

class ChargingSession{
	public int $chargeDuration; //sec
	public ?float $avgChargePower = null;
	public ?float $maxChargePower = null;
	public ?float $minChargePower = null;
	public float $integralChargeEnergy; //kWs
	public int $rangeStart;
	public int $rangeEnd;
	public int $targetSOC;
	public int $socStart;
	public int $socEnd;
	
	public ?string $chargeStartTime = null;
	public ?string $chargeEndTime = null;
	public string $startTime;
	public ?string $endTime = null;
	
	public function toArray(): array{
		return [
			"startTime" => $this->startTime,
			"endTime" => $this->endTime,
			"chargeStartTime" => $this->chargeStartTime,
			"chargeEndTime" => $this->chargeEndTime,
			"duration" => $this->chargeDuration,
			"avgChargePower" => $this->avgChargePower,
			"maxChargePower" => $this->maxChargePower,
			"minChargePower" => $this->minChargePower,
			"chargeEnergy" => $this->integralChargeEnergy,
			"rangeStart" => $this->rangeStart,
			"rangeEnd" => $this->rangeEnd,
			"targetSOC" => $this->targetSOC,
			"socStart" => $this->socStart,
			"socEnd" => $this->socEnd
		];
	}
}

class chargingSessionDataProvider{
	private int $beginTime;
	private int $endTime;
	
	private float $took;
	
	public function __construct(int $beginTime, int $endTime){
		$this->beginTime = $beginTime;
		$this->endTime = $endTime;
	}
	
	private static function formatDate(string $dbDate): string{
		return ((new DateTime($dbDate))->format(DateTimeInterface::ATOM));
	}
	
	private static function nullableFormatDate(?string $dbDate): ?string{
		return $dbDate === null ? null : self::formatDate($dbDate);
	}
	
	public function getChargingSessions(): array{
		$beginTime = microtime(true);
		$data = $this->fetchFromDB($this->beginTime, $this->endTime);
		
		$chargingSessions = [];
		foreach($data as $chargeSessionData){
			$chargingSession = new ChargingSession();
			$chargingSession->id = $chargeSessionData["sessionid"];
			$chargingSession->startTime = self::formatDate($chargeSessionData["starttime"]);
			$chargingSession->endTime = self::nullableFormatDate($chargeSessionData["endtime"]);
			$chargingSession->chargeStartTime = self::nullableFormatDate($chargeSessionData["chargestarttime"]);
			$chargingSession->chargeEndTime = self::nullableFormatDate($chargeSessionData["chargeendtime"]);
			$chargingSession->chargeDuration = (int) $chargeSessionData["duration"];
			$chargingSession->avgChargePower = (float) $chargeSessionData["avgchargepower"];
			$chargingSession->maxChargePower = (float) $chargeSessionData["maxchargepower"];
			$chargingSession->minChargePower = (float) $chargeSessionData["minchargepower"];
			$chargingSession->integralChargeEnergy = (float) $chargeSessionData["chargeenergy"];
			$chargingSession->rangeStart = (int) $chargeSessionData["rangestart"];
			$chargingSession->rangeEnd = (int) $chargeSessionData["rangeend"];
			$chargingSession->targetSOC = (int) $chargeSessionData["targetsoc"];
			$chargingSession->socStart = (int) $chargeSessionData["socstart"];
			$chargingSession->socEnd = (int) $chargeSessionData["socend"];
			
			$chargingSessions[] = $chargingSession;
		}
		
		$endTime = microtime(true);
		$this->took = $endTime - $beginTime;
		
		return $chargingSessions;
	}
	
	public function getTook(): float{
		return $this->took;
	}
	
	private function fetchFromDB(int $beginTime, int $endTime): array{
		$beginTime = new DateTime("@".$beginTime, new DateTimeZone("UTC"));
		$endTime = new DateTime("@".$endTime, new DateTimeZone("UTC"));
		$data = DatabaseConnection::getInstance()->query(
			"SELECT starttime,
			endtime,
			chargestarttime,
			chargeendtime,
			duration,
			avgchargepower,
			maxchargepower,
			minchargepower,
       		chargeenergy,
			rangestart,
			rangeend,
			targetsoc,
			socstart,
            socend FROM chargingSessions WHERE starttime >= TIMESTAMP '".
			$beginTime->format("Y-m-d\TH:i:s").
			"' AND starttime <= TIMESTAMP '".$endTime->format("Y-m-d\TH:i:s")."' ORDER BY startTime DESC"
		);
		#var_dump($data);
		return $data;
	}
}