<?php
declare(strict_types=1);

namespace robske_110\vwid;

use DOMDocument;
use robske_110\utils\Logger;
use robske_110\vwid\exception\VWLoginException;
use robske_110\webutils\CurlError;
use robske_110\webutils\CurlWrapper;
use robske_110\webutils\Form;

class MobileAppLogin extends CurlWrapper{
	const LOGIN_BASE = "https://login.apps.emea.vwapps.io";
	const LOGIN_HANDLER_BASE = "https://identity.vwgroup.io";
	const API_BASE = "https://mobileapi.apps.emea.vwapps.io";
	
	private array $weConnectRedirFields = [];
	
	private array $appTokens = [];
	
	public function __construct(LoginInformation $loginInformation){
		parent::__construct();
		Logger::debug("Loading login Page...");

		libxml_use_internal_errors(true);

		$loginPage = $this->getRequest(self::LOGIN_BASE."/authorize", [
			"nonce" => base64_encode(random_bytes(12)),
			"redirect_uri" => "weconnect://authenticated"
		]);
		
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($loginPage);
		
		$form = new Form($dom->getElementById("emailPasswordForm"));
		$fields = $form->getHiddenFields();
		#var_dump($fields);
		$fields["email"] = $loginInformation->username;
		
		Logger::debug("Sending email...");
		var_dump($form->getAttribute("action"));
		$pwdPage = $this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		
		#var_dump($pwdPage);
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($pwdPage);
		
		$form = new Form($dom->getElementById("credentialsForm"));
		$fields = $form->getHiddenFields();
		$fields["password"] = $loginInformation->password;
		#var_dump($fields);
		
		Logger::debug("Sending password ...");
		try{
			$nextPage = $this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		}catch(CurlError $curlError){
			if($curlError->curlErrNo !== 1){
				throw $curlError;
			}
		}
		
		if(empty($this->weConnectRedirFields)){
			throw new VWLoginException("Unable to login. Could not find location header.");
		}
		var_dump($this->weConnectRedirFields);
		Logger::debug("Getting real token...");
		$this->appTokens = json_decode($this->postRequest("https://login.apps.emea.vwapps.io/login/v1", json_encode([
			"state" => $this->weConnectRedirFields["state"],
			"id_token" => $this->weConnectRedirFields["id_token"],
			"redirect_uri" => "weconnect://authenticated",
			"region" => "emea",
			"access_token" => $this->weConnectRedirFields["access_token"],
			"authorizationCode" => $this->weConnectRedirFields["code"]
		]), [
			"accept: */*",
			"content-type: application/json",
			"x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
			"user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
			"accept-language: de-de",
		]), true);
	}
	
	protected function onHeaderEntry(string $entryName, string $entryValue){
		if($entryName == "location"){
			if(str_starts_with($entryValue, "weconnect://")){
				$args = explode("&", substr($entryValue, strlen("weconnect://authenticated#")));
				foreach($args as $field){
					$field = explode("=", $field);
					$this->weConnectRedirFields[$field[0]] = $field[1];
				}
			}
		}
	}
	
	public function getAppTokens(): array{
		return $this->appTokens;
	}
}
