<?php


namespace robske_110\vwid\integrations;

use DateTime;
use DateTimeZone;
use JsonException;
use RuntimeException;
use robske_110\utils\ErrorUtils;
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

		Logger::var_dump($postData, "ABRP PostData");

		$curlWrapper = new CurlWrapper();
		$response = $curlWrapper->postRequest(self::API_SEND_ENDPOINT, http_build_query($postData), [
			"Authorization: APIKEY ".($this->apiKey ?? self::API_KEY)
		]);


		Logger::var_dump($response, "ABRP response");

		$httpCode = curl_getinfo($curlWrapper->getCh(), CURLINFO_RESPONSE_CODE);
		if($httpCode === 401){
			if(str_contains($response, "Token")){
				throw new RuntimeException(
					"ABRP API returned ".$response.": Check user-token, or copy a new one from the ABRP app!"
				);
			}
			if(str_contains($response, "Key")){
				if($this->apiKey !== null){
					throw new RuntimeException(
						"ABRP API returned ".$response.": API key specified in config is invalid, try again without one!"
					);
				}else{
					throw new RuntimeException("ABRP API returned ".$response.": Inbuilt API key seems invalid");
				}
			}
			throw new RuntimeException("ABRP API returned: (httpCode 401) ".$response);
		}
		if($httpCode !== 200){
			throw new RuntimeException("ABRP API returned: (httpCode ".$httpCode.") ".$response);
		}

		try{
			$response = json_decode($response, associative: true, flags: JSON_THROW_ON_ERROR);
		}catch(JsonException $jsonException){
			Logger::critical("Error while decoding json in ABRP response");
			ErrorUtils::logException($jsonException);
			throw new RuntimeException("ABRP API: Could not decode response");
		}

		if($response["status"] !== "ok"){
			Logger::warning("ABRP API: status is not ok");
		}
	}
}