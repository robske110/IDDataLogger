<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use DOMDocument;
use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDLoginException;
use robske_110\webutils\CurlError;
use robske_110\webutils\Form;

class MobileAppAPI extends API{
	const LOGIN_BASE = "https://login.apps.emea.vwapps.io";
	const LOGIN_HANDLER_BASE = "https://identity.vwgroup.io";
	const API_BASE = "https://mobileapi.apps.emea.vwapps.io";
	
	private LoginInformation $loginInformation;
	
	private array $weConnectRedirFields = [];
	
	private array $appTokens = [];
	
	public function __construct(LoginInformation $loginInformation){
		parent::__construct();
		$this->loginInformation = $loginInformation;
	}
	
	public function login(){
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
		$fields["email"] = $this->loginInformation->username;
		
		Logger::debug("Sending email...");
		$pwdPage = $this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($pwdPage);
		
		if($dom->getElementById("credentialsForm") === null){
			throw new IDLoginException("Unable to login. Check login information (e-mail)! (Could not find credentialsForm)");
		}
		$form = new Form($dom->getElementById("credentialsForm"));
		$fields = $form->getHiddenFields();
		$fields["password"] = $this->loginInformation->password;
		
		Logger::debug("Sending password ...");
		try{
			$this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		}catch(CurlError $curlError){
			if($curlError->curlErrNo !== 1){
				throw $curlError;
			}
		}
		
		if(empty($this->weConnectRedirFields)){
			throw new IDLoginException("Unable to login. Check login information (password)! See FAQ if issue persists. (Could not find location header)");
		}
		#var_dump($this->weConnectRedirFields);
		Logger::debug("Getting real token...");
		$this->appTokens = $this->apiPost("login/v1", json_encode([
			"state" => $this->weConnectRedirFields["state"],
			"id_token" => $this->weConnectRedirFields["id_token"],
			"redirect_uri" => "weconnect://authenticated",
			"region" => "emea",
			"access_token" => $this->weConnectRedirFields["access_token"],
			"authorizationCode" => $this->weConnectRedirFields["code"]
		]),self::LOGIN_BASE, [
			"content-type: application/json"
		]);
	}
	
	protected function onHeaderEntry(string $entryName, string $entryValue){
		if(strtolower($entryName) == "location"){ //curl sometimes has headers in all lowercase
			if(str_starts_with($entryValue, "weconnect://")){
				$args = explode("&", substr($entryValue, strlen("weconnect://authenticated#")));
				foreach($args as $field){
					$field = explode("=", $field);
					$this->weConnectRedirFields[$field[0]] = $field[1];
				}
			}
		}
	}
	
	public function apiGet(string $apiEndpoint, array $fields = [], string $apiBase = self::API_BASE, ?array $header = null): array{
		if($header === null){
			$header = [
				"Accept: application/json",
				"Authorization: Bearer ".$this->appTokens["accessToken"]
			];
		}
		$response = $this->getRequest($apiBase."/".$apiEndpoint, $fields, $header);
		return $this->verifyAndDecodeResponse($response, $apiEndpoint);
	}
	
	public function apiPost(string $apiEndpoint, $body, string $apiBase = self::API_BASE, ?array $header = null): array{
		if($header === null){
			$header = [
				"content-type: application/json",
				"Authorization: Bearer ".$this->appTokens["accessToken"]
			];
		}
		$response = $this->postRequest($apiBase."/".$apiEndpoint, $body, $header);
		return $this->verifyAndDecodeResponse($response, $apiEndpoint);
	}
	
	/**
	 * Tries to refresh the tokens
	 *
	 * @return bool Returns whether the token refresh was successful. If false is returned consider calling login()
	 */
	public function refreshToken(): bool{
		try{
			$this->appTokens = $this->apiGet("refresh/v1", [], self::LOGIN_BASE,  [
				"content-type: application/json",
				"Authorization: Bearer ".$this->appTokens["refreshToken"]
			]);
			Logger::var_dump($this->appTokens, "new appTokens");
		}catch(\RuntimeException $exception){
			Logger::warning("Failed to refresh token");
			ErrorUtils::logException($exception);
			return false;
		}
		return true;
	}
	
	public function getAppTokens(): array{
		return $this->appTokens;
	}
}
