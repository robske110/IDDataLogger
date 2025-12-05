<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use DOMDocument;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDLoginException;
use robske_110\webutils\Form;

class WebsiteAPI extends API{
	const LOGIN_PAGE = "https://www.volkswagen.de/app/authproxy/login?fag=vw-de,vwag-weconnect&scope-vw-de=profile,address,phone,carConfigurations,dealers,cars,vin,profession&scope-vwag-weconnect=openid&prompt-vw-de=login&prompt-vwag-weconnect=none&redirectUrl=https://www.volkswagen.de/de/besitzer-und-nutzer/myvolkswagen/garage.html";
	
	private string $csrf;

	public function __construct(LoginInformation $loginInformation){
		parent::__construct();
		Logger::debug("Loading login Page...");
		
		libxml_use_internal_errors(true);
		
		$loginPage = $this->getRequest(self::LOGIN_PAGE);
		
		$loggedInPage = $this->loginPage($loginPage, $loginInformation);
		
		if(empty($this->csrf)){
			Logger::var_dump($loggedInPage ?? "not defined", "loggedInPage");
			throw new IDLoginException("Failed to login");
		}
	}
	
	public function apiGetCSRF(string $uri, array $fields = [], ?array $header = null): array{
		$header = ($header ?? []) + [
			"Accept: application/json",
			"X-csrf-token: ".$this->csrf
		];
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
