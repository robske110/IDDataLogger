<?php
/*class UserData{
	private $username;
	private $password;
	private $vin;
}*/

$id3Login = new ID3Login();

function debug($str){
	echo($str.PHP_EOL);
}

class Form{
	private DOMElement $formElement;
	
	public function __construct(DOMElement $ele){
		$this->formElement = $ele;
	}
	
	public function getAttribute(string $name): string{
		return $this->formElement->attributes->getNamedItem($name)->textContent;
	}
	
	public function getHiddenFields(): array{
		$fields = [];
		foreach($this->formElement->childNodes as $childNode){
			if($childNode->nodeName == "input" && $childNode->attributes->getNamedItem("type")->textContent == "hidden"){
				$fields[$childNode->attributes->getNamedItem("name")->textContent] = $childNode->attributes->getNamedItem("value")->textContent;
			}
		}
		return $fields;
	}
}

class ID3Login{
	const USERNAME = "";
	const PWD = "";
	
	public $ch;
	
	public function setCurlOption(int $curlOption, $value){
		if(!curl_setopt($this->ch, $curlOption, $value)){
			throw new Exception("Failed to set curl option ".$curlOption." to ".print_r($value, true));
		}
	}
	
	public function __construct($enableVerbose = false){
		debug("Loading login Page...");

		libxml_use_internal_errors(true);

		$this->ch = curl_init();
		$this->setCurlOption(CURLOPT_VERBOSE, $enableVerbose);
		$this->setCurlOption(CURLOPT_COOKIEFILE, ""); //enable cookie handling
		$this->setCurlOption(CURLOPT_FOLLOWLOCATION, true);
		$this->setCurlOption(CURLOPT_RETURNTRANSFER, true);
		#curl_setopt($this->ch, CURLOPT_URL, "https://www.volkswagen.de/app/authproxy/login?fag=vw-de,vwag-weconnect&scope-vw-de=profile,address,phone,carConfigurations,dealers,cars,vin,profession&scope-vwag-weconnect=openid&prompt-vw-de=login&prompt-vwag-weconnect=none&redirectUrl=https://www.volkswagen.de/de/besitzer-und-nutzer/myvolkswagen.html");
		curl_setopt($this->ch, CURLOPT_URL, "https://login.apps.emea.vwapps.io/authorize?nonce=".base64_encode(random_bytes(12))."&redirect_uri=weconnect://authenticated");
		
		$loginPage = curl_exec($this->ch);
		#var_dump($loginPage);
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		
		$dom->loadHTML($loginPage);
		$form = new Form($dom->getElementById("emailPasswordForm"));
		$fields = $form->getHiddenFields();
		#var_dump($fields);
		$fields["email"] = self::USERNAME;
		
		curl_setopt($this->ch, CURLOPT_URL, "https://identity.vwgroup.io".$form->getAttribute("action"));
		var_dump($form->getAttribute("action"));

		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields);
		
		debug("Sending email...");
		$pwdPage = curl_exec($this->ch);
		#var_dump($pwdPage);
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		
		$dom->loadHTML($pwdPage);
		$form = new Form($dom->getElementById("credentialsForm"));
		$fields = $form->getHiddenFields();
		$fields["password"] = self::PWD;
		var_dump($fields);
		curl_setopt($this->ch, CURLOPT_URL, "https://identity.vwgroup.io".$form->getAttribute("action"));

		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, [$this, "curlHeader"]);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields);
		debug("Sending password ...");
		$nextPage = curl_exec($this->ch);
		var_dump($nextPage);
	}
	
	private static function parseHeaderLine(string $headerLine){
		$colonPos = strpos($headerLine, ":");
		$name = substr($headerLine, 0, $colonPos);
		return [$name, substr($headerLine, $colonPos+2)];
	}
	
	private function curlHeader($ch, $headerLine){
		#debug("HEADER_____:$headerLine");
		$headerEntry = self::parseHeaderLine($headerLine);
		if($headerEntry[0] == "location"){
			if(strpos($headerEntry[1], "weconnect://") === 0){
				$this->weconnectRedirect = $headerEntry[1];
			}
		}
		if(($setCookiePos = strpos($headerLine, "set-cookie: ")) !== false){
			#debug("COOOOOOOOOOKKIIIIIIIEEEE:");
			$posAssign = strpos($headerLine, "=");
			$cookieName = substr($headerLine, strlen("set-cookie: "), $posAssign-strlen("set-cookie: "));
			$posSemicolon = strpos($headerLine, ";");
			$cookieContent = substr($headerLine, $posAssign+1, $posSemicolon-$posAssign-1);
			if($cookieName == "csrf_token"){
				$this->csrf = $cookieContent;
			}
		}
		return strlen($headerLine);
	}
	
	public function getCsrf(){
		return $this->csrf;
	}
	
	public function getWeconnectRedirect(){
		return $this->weconnectRedirect;
	}
	
	public function authenticatedGetRequest(string $str, array $fields = [], array $header = []){
		curl_setopt($this->ch, CURLOPT_POST, false);
		curl_setopt($this->ch, CURLOPT_URL, $str.HTTPUtils::makeFieldStr($fields));
		#$header[] = "Connection: keep-alive";
		#var_dump($header);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		return curl_exec($this->ch);	
	}
	
	public function authenticatedPostRequest(string $url, string $body, array $header = []){
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($this->ch, CURLOPT_URL, $url);
		#$header[] = "Connection: keep-alive";
		var_dump($header);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		return curl_exec($this->ch);
		
	}
	
	public function getCh(){
		return $this->ch;
	}
}

final class HTTPUtils{
	public static final function makeFieldStr(array $fields): string{
		if(empty($fields)){
			return "";
		}
		$fieldStr = "?";
		foreach($fields as $fieldName => $fieldValue){
			$fieldStr.= $fieldName."=".$fieldValue."&";
		}
		return $fieldStr;
	}
}

$weCR = $id3Login->getWeconnectRedirect();
debug("___weCR");
var_dump($weCR);
$weCR = explode("&", substr($weCR, strlen("weconnect://authenticated#")));
$fields = [];
foreach($weCR as $field){
	$field = explode("=", $field);
	$fields[$field[0]] = $field[1];
}
debug("getting real token because why not");
$appTokens = json_decode($id3Login->authenticatedPostRequest("https://login.apps.emea.vwapps.io/login/v1", json_encode([
	"state" => $fields["state"],
	"id_token" => $fields["id_token"],
	"redirect_uri" => "weconnect://authenticated",
	"region" => "emea",
	"access_token" => $fields["access_token"],
	"authorizationCode" => $fields["code"]
]), [
	"accept: */*",
	"content-type: application/json",
    "x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
    "user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
    "accept-language: de-de",
]), true);

var_dump($appTokens);

debug("getting cardata");
var_dump($id3Login->authenticatedGetRequest("https://mobileapi.apps.emea.vwapps.io/vehicles/" ."VIN". "/status", [], [
	"accept: */*",
	"content-type: application/json",
	"content-version: 1",
    "x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
    "user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
    "accept-language: de-de",
	"Authorization: Bearer ".$appTokens["accessToken"],
	//"Host: customer-profile.apps.emea.vwapps.io",
]));
exit;

debug("user:");
$userInfo = json_decode($id3Login->authenticatedGetRequest("https://www.volkswagen.de/app/authproxy/vw-de/user", [], ["Accept: application/json", "X-csrf-token: ".$id3Login->getCsrf()]), true);
var_dump($userInfo);
debug("accesstokencar:");
$tokens = json_decode($id3Login->authenticatedGetRequest("https://www.volkswagen.de/app/authproxy/vw-de/tokens", [], ["Accept: application/json", "X-csrf-token: ".$id3Login->getCsrf()]), true);
var_dump($tokens);
$accessTokenCar = $tokens["access_token"];
debug("carlist:");
var_dump($id3Login->authenticatedGetRequest("https://myvwde.cloud.wholesaleservices.de/api/tbo/cars", [], ["Accept: application/json", "Authorization: Bearer ".$accessTokenCar]));
#https://w1hub-backend-production.apps.emea.vwapps.io/cars is empty ???

debug("accesstokenweconnect:");
$accessTokenWeConnect = json_decode($id3Login->authenticatedGetRequest("https://www.volkswagen.de/app/authproxy/vwag-weconnect/tokens", [], ["Accept: application/json", "X-csrf-token: ".$id3Login->getCsrf()]), true)["access_token"];
debug("accesstokenweconnectexchange:");
$accessTokenWeConnect = $id3Login->authenticatedGetRequest("https://myvw-idk-token-exchanger.apps.emea.vwapps.io/token-exchange?isWcar=true", [], ["Accept: application/json, text/plain", "Authorization: Bearer ".$accessTokenWeConnect]);

/*var_dump($id3Login->authenticatedGetRequest("https://login.apps.emea.vwapps.io/authorize?nonce=&redirect_uri=weconnect://authenticated", [], [
	"Host: login.apps.emea.vwapps.io"
]));*/


#debug("fuelstatus:");
#var_dump(json_decode($id3Login->authenticatedGetRequest("https://cardata.apps.emea.vwapps.io/vehicles/VIN/fuel/status", [], ["Accept: application/json", "Authorization: Bearer ".$accessTokenWeConnect, "User-Id: ".$userInfo["sub"]]), true));

var_dump($id3Login->authenticatedGetRequest("https://customer-profile.apps.emea.vwapps.io/v1/customers/" . $userInfo["sub"] . "/realCarData", [], [
	"user-agent: okhttp/3.7.0",
	"Accept: application/json",
	"Authorization: Bearer ".$accessTokenCar,
	"Host: customer-profile.apps.emea.vwapps.io",
]));

/*var_dump($id3Login->authenticatedGetRequest("https://mobileapi.apps.emea.vwapps.io/vehicles/", [], [
	"accept: #*#/#*",
	"content-type: application/json",
	"content-version: 1",
    "x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
    "user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
    "accept-language: de-de",
	"Authorization: Bearer ".$accessTokenWeConnect,
	//"Host: customer-profile.apps.emea.vwapps.io",
]));*/


#var_dump($id3Login->authenticatedGetRequest("https://myvw-idk-token-exchanger.apps.emea.vwapps.io/token-exchange?isWcar=true", [], ["Accept: application/json", "Authorization: Bearer " .$id3Login->getCsrf()]));