<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use DOMDocument;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDLoginException;
use robske_110\webutils\Form;

class WebsiteAPI extends API{
	const LOGIN_PAGE = "https://www.volkswagen.de/app/authproxy/login?fag=vw-de,vwag-weconnect&scope-vw-de=profile,address,phone,carConfigurations,dealers,cars,vin,profession&scope-vwag-weconnect=openid&prompt-vw-de=login&prompt-vwag-weconnect=none&redirectUrl=https://www.volkswagen.de/de/besitzer-und-nutzer/myvolkswagen/garage.html";
	const LOGIN_HANDLER_BASE = "https://identity.vwgroup.io";
	
	private string $csrf;
	
	private string $apToken;
	
	public function __construct(LoginInformation $loginInformation){
		parent::__construct();
		Logger::debug("Loading login Page...");
		
		libxml_use_internal_errors(true);
		
		$loginPage = $this->getRequest(self::LOGIN_PAGE);
		
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
		
		if($dom->getElementById("credentialsForm") === null){
			Logger::var_dump($pwdPage, "pwdPage");
			throw new IDLoginException("Unable to login. Check login information (e-mail)! (Could not find credentialsForm)");
		}
		$form = new Form($dom->getElementById("credentialsForm"));
		$fields = $form->getHiddenFields();
		$fields["password"] = $loginInformation->password;
		if(self::$VERBOSE) Logger::var_dump($fields, "credentialsForm");
		
		Logger::debug("Sending password ...");
		$this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		
		if(empty($this->csrf)){
			throw new IDLoginException("Failed to login");
		}
	}
	
	public function getAPtoken(){
		if(!isset($this->apToken)){
			sleep(1); //If we just logged in VW servers might not have synced all login information correctly yet
			$this->apToken = $this->apiGetCSRF("https://www.volkswagen.de/app/authproxy/vw-de/tokens")["access_token"];
		}
		return $this->apToken;
	}
	
	public function apiGetAP(string $uri, array $fields = [], ?array $header = null): array{
		if($header === null){
			$header = [
				"Accept: application/json",
				"Authorization: Bearer ".$this->getAPtoken()
			];
		}
		$response = $this->getRequest($uri, $fields, $header);
		return $this->verifyAndDecodeResponse($response, $uri);
	}
	
	public function apiGetCSRF(string $uri, array $fields = [], ?array $header = null): array{
		if($header === null){
			$header = [
				"Accept: application/json",
				"X-csrf-token: ".$this->csrf
			];
		}
		$response = $this->getRequest($uri, $fields, $header);
		return $this->verifyAndDecodeResponse($response, $uri);
	}
	
	protected function onCookie(string $name, string $content){
		if($name == "csrf_token"){
			$this->csrf = $content;
		}
	}
	
	public function getCsrf(): string{
		return $this->csrf;
	}
}
