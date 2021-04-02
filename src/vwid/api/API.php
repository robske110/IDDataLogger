<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDAPIException;
use robske_110\vwid\api\exception\IDAuthorizationException;
use robske_110\webutils\CurlWrapper;

class API extends CurlWrapper{
	public static bool $VERBOSE = false;
	
	public function __construct(?bool $enableVerbose = null){
		parent::__construct($enableVerbose ?? self::$VERBOSE);
	}
	
	public function verifyAndDecodeResponse(string $response, string $apiEndpoint = "unknown"): array{
		$httpCode = curl_getinfo($this->getCh(), CURLINFO_RESPONSE_CODE);
		if($httpCode === 401){
			throw new IDAuthorizationException("Not authorized to execute API request '".$apiEndpoint."' (httpCode 401)");
		}
		if($httpCode !== 200 && $httpCode !== 202 && $httpCode !== 207){
			throw new IDAPIException("API request '".$apiEndpoint."' failed with httpCode ".$httpCode);
		}
		if(str_contains($response, "Unauthorized")){
			Logger::debug("Got: ".$response);
			throw new IDAuthorizationException("Not authorized to execute API request '".$apiEndpoint."' (response contained Unauthorized)");
		}
		try{
			return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException $jsonException){
			Logger::critical("Error while json decoding API request '".$apiEndpoint."'");
			ErrorUtils::logException($jsonException);
			throw new IDAPIException("Could not decode json for request '".$apiEndpoint."'");
		}
	}
}