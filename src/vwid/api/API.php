<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use DOMDocument;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDAPIException;
use robske_110\vwid\api\exception\IDAuthorizationException;
use robske_110\vwid\api\exception\IDLoginException;
use robske_110\webutils\CurlWrapper;
use robske_110\webutils\Form;

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
	
	public function emailLoginStep(string $loginPage, LoginInformation $loginInformation){
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($loginPage);
		
		$form = new Form($dom->getElementById("emailPasswordForm"));
		$fields = $form->getHiddenFields();
		if(self::$VERBOSE) Logger::var_dump($fields, "emailPasswordForm");
		$fields["email"] = $loginInformation->username;
		
		Logger::debug("Sending email...");
		$pwdPage = $this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($pwdPage);
		
		if($dom->getElementById("emailPasswordForm") !== null){
			Logger::var_dump($pwdPage, "pwdPage");
			throw new IDLoginException("Unable to login. Check login information (e-mail)! (Still found emailPasswordForm)");
		}
		$fields["password"] = $loginInformation->password;
		
		$errorString =
			"Unable to login. Most likely caused by an unexpected change on VW's side.".
			" Check login information. If issue persists, open an issue!";
		$hmac = preg_match("/\"hmac\":\"([^\"]*)\"/", $pwdPage, $matches);
		if(!$hmac){
			Logger::var_dump($pwdPage, "pwdPage");
			throw new IDLoginException($errorString." (could not find hmac)");
		}
		$fields["hmac"] = $matches[1];
		
		//Note: this could also be parsed from postAction
		$action = preg_replace("/(?<=\/)[^\/]*$/", "authenticate", $form->getAttribute("action"));
		if($action === $form->getAttribute("action") || $action === null){
			Logger::var_dump($pwdPage, "pwdPage");
			throw new IDLoginException($errorString." (action did not match expected format)");
		}
		
		return [$action, $fields];
	}
}