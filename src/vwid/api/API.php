<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use DOMDocument;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDAPIException;
use robske_110\vwid\api\exception\IDAuthorizationException;
use robske_110\vwid\api\exception\IDLoginException;
use robske_110\webutils\CurlError;
use robske_110\webutils\CurlWrapper;
use robske_110\webutils\Form;
use robske_110\webutils\HTTPUtils;

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
	
	const LOGIN_HANDLER_BASE = "https://identity.vwgroup.io";
	
	public function loginPage(string $loginPage, LoginInformation $loginInformation){
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($loginPage);
		
		$form = new Form($dom->getElementsByTagName("form")->item(0));
		$fields = $form->getHiddenFields();
		if(self::$VERBOSE) Logger::var_dump($fields, "emailPasswordForm");
		$fields["username"] = $loginInformation->username;
		$fields["password"] = $loginInformation->password;
		
		Logger::debug("Sending emailPasswordForm...");
		try{
			$loggedInPage = $this->postRequest(curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL), HTTPUtils::makeFieldStr($fields));
		}catch(CurlError $curlError){
			if($curlError->curlErrNo !== 1){
				throw $curlError;
			}
		}

		return $loggedInPage ?? null;
	}
}