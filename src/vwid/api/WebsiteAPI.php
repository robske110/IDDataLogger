<?php
declare(strict_types=1);

namespace robske_110\vwid\api;

use DOMDocument;
use robske_110\utils\Logger;
use robske_110\vwid\api\exception\IDLoginException;
use robske_110\webutils\CurlError;
use robske_110\webutils\Form;
use robske_110\webutils\HTTPUtils;

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
			//TODO: This should probably be somehow moved to the Wizard. This is a quick implementation for now.
			if(str_contains($loggedInPage, "error-cs-code-required")){
				Logger::notice("VW sent you a code via email (only required for car picture fetching). Press enter if you want to enter the code or do nothing for 15 seconds to skip.");
				echo("Press enter!");
				$read = [STDIN];
				$empty = [];
				if(stream_select($read, $empty, $empty, seconds: 15)){
					fgets(STDIN);
					echo("> Enter code: ");
					$code = trim((string) fgets(STDIN));
					echo("code:".$code);

					$dom = new DOMDocument();
					$dom->strictErrorChecking = false;
					$dom->loadHTML($loggedInPage);

					$form = new Form($dom->getElementsByTagName("form")->item(0));
					$fields = $form->getHiddenFields();
					if(self::$VERBOSE) Logger::var_dump($fields, "codeForm");
					$fields["code"] = $code;

					Logger::debug("Sending codeForm...");
					try{
						$loggedInPage = $this->postRequest(curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL), HTTPUtils::makeFieldStr($fields));
					}catch(CurlError $curlError){
						if($curlError->curlErrNo !== 1){
							throw $curlError;
						}
					}
					if(empty($this->csrf)){
						Logger::var_dump($loggedInPage ?? "not defined", "loggedInPage");
						throw new IDLoginException("Failed to login (Incorrect email code)");
					}
					return;
				}else{
					throw new IDLoginException("E-Mail code required for login, but none provided");
				}
			}
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
