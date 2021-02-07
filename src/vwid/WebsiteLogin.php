<?php
declare(strict_types=1);

namespace robske_110\vwid;

use DOMDocument;
use robske_110\webutils\CurlError;
use robske_110\webutils\CurlWrapper;
use robske_110\webutils\Form;

class WebsiteLogin extends CurlWrapper{
	const LOGIN_PAGE = "https://www.volkswagen.de/app/authproxy/login?fag=vw-de,vwag-weconnect&scope-vw-de=profile,address,phone,carConfigurations,dealers,cars,vin,profession&scope-vwag-weconnect=openid&prompt-vw-de=login&prompt-vwag-weconnect=none&redirectUrl=https://www.volkswagen.de/de/besitzer-und-nutzer/myvolkswagen/garage.html";
	const LOGIN_HANDLER_BASE = "https://identity.vwgroup.io";
	const API_BASE = "https://mobileapi.apps.emea.vwapps.io";
	
	private array $weConnectRedirFields = [];
	
	private string $csrf;
	
	public function __construct(LoginInformation $loginInformation){
		parent::__construct();
		debug("Loading login Page...");
		
		libxml_use_internal_errors(true);
		
		$loginPage = $this->getRequest(self::LOGIN_PAGE);
		
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($loginPage);
		
		$form = new Form($dom->getElementById("emailPasswordForm"));
		$fields = $form->getHiddenFields();
		#var_dump($fields);
		$fields["email"] = $loginInformation->username;
		
		debug("Sending email...");
		$pwdPage = $this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		
		#var_dump($pwdPage);
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadHTML($pwdPage);
		
		$form = new Form($dom->getElementById("credentialsForm"));
		$fields = $form->getHiddenFields();
		$fields["password"] = $loginInformation->password;
		#var_dump($fields);
		
		debug("Sending password ...");
		$nextPage = $this->postRequest(self::LOGIN_HANDLER_BASE.$form->getAttribute("action"), $fields);
		
		#var_dump($nextPage);
		
		if(empty($this->csrf)){
			//error;
			debug("Failed");
		}
		
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
