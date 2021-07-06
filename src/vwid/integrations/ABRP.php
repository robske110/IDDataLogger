<?php


namespace robske_110\vwid\integrations;

use DateTime;
use DateTimeZone;
use robske_110\utils\Logger;
use robske_110\vwid\CarStatusWrittenUpdateReceiver;
use robske_110\webutils\CurlWrapper;
use robske_110\webutils\HTTPUtils;

class ABRP implements CarStatusWrittenUpdateReceiver{
	private string $apiKey;
	private string $userToken;

	public function __construct(string $apiKey, string $userToken){
		$this->apiKey = $apiKey;
		$this->userToken = $userToken;
	}

	public function carStatusWrittenUpdate(array $carStatus){
		$postData = ["api_key" => $this->apiKey, "token" => $this->userToken, "tlm" => json_encode([
			"utc" => (new DateTime($carStatus["time"], new DateTimeZone("UTC")))->getTimestamp(),
			"soc" => $carStatus["batterySOC"],
			"power" => - (float) $carStatus["chargePower"], //this will only be charge power (sent negative), otherwise zero
			"is_charging" => (int) ($carStatus["chargeState"] == "charging"),
			//"kwh_charged" TODO: Get this out of the chargesession table / in memory chargeSession
		])];

		Logger::var_dump($postData, "PostData for ABRP");

		$curlWrapper = new CurlWrapper();
		$response = $curlWrapper->postRequest("https://api.iternio.com/1/tlm/send".HTTPUtils::makeFieldStr($postData), ""/*, [
			"Authorization" => "APIKEY ".$this->apiKey
		]*/);

		Logger::var_dump($response, "Response from ABRP");
	}
}