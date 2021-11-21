<?php


namespace robske_110\vwid\integrations;

use DateTime;
use DateTimeZone;
use robske_110\utils\Logger;
use robske_110\vwid\CarStatusWrittenUpdateReceiver;
use robske_110\vwid\chargesession\ChargeSessionHandler;
use robske_110\webutils\CurlWrapper;

class ABRP implements CarStatusWrittenUpdateReceiver{
	const API_SEND_ENDPOINT = "https://api.iternio.com/1/tlm/send";
	const API_KEY = "c74f9572-f1e3-4cc8-825b-81edf700c408";

	private ChargeSessionHandler $chargeSessionHandler;
	private string $userToken;
	private ?string $apiKey;

	public function __construct(string $userToken, ?string $apiKey, ChargeSessionHandler $chargeSessionHandler){
		$this->chargeSessionHandler = $chargeSessionHandler;
		$this->userToken = $userToken;
		$this->apiKey = $apiKey;
	}

	public function carStatusWrittenUpdate(array $carStatus){
		$postData = ["token" => $this->userToken, "tlm" => json_encode([
			"utc" => (new DateTime($carStatus["time"], new DateTimeZone("UTC")))->getTimestamp(),
			"soc" => $carStatus["batterySOC"],
			"power" => - (float) $carStatus["chargePower"], //this will only be charge power (sent negative), otherwise zero
			"is_charging" => (int) ($carStatus["chargeState"] == "charging"),
			"kwh_charged" => round(
				$this->chargeSessionHandler->getCurrentChargingSession()?->integralChargeEnergy / 3600, 2
			),
			"est_battery_range" => $carStatus["remainingRange"]
		])];

		Logger::var_dump($postData, "PostData for ABRP");

		$curlWrapper = new CurlWrapper();
		$response = $curlWrapper->postRequest(self::API_SEND_ENDPOINT, http_build_query($postData), [
			"Authorization: APIKEY ".($this->apiKey ?? self::API_KEY)
		]);

		Logger::var_dump($response, "Response from ABRP");
	}
}