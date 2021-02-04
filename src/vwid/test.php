<?php
declare(strict_types=1);

use robske_110\vwid\LoginInformation;
use robske_110\vwid\MobileAppLogin;

const BASE_DIR = __DIR__."/../../";

require(BASE_DIR."src/Autoloader.php");

function debug($str){
	echo($str.PHP_EOL);
}

$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);

$id3Login = new MobileAppLogin(new LoginInformation($config["username"], $config["password"]));


$vehicles = json_decode($id3Login->getRequest("https://mobileapi.apps.emea.vwapps.io/vehicles", [], [
	"Authorization: Bearer ".$id3Login->getAppTokens()["accessToken"]
]), true)["data"];
var_dump($vehicles);

$vehicleToUse = $vehicles[0];
if(!empty($config["vin"])){
	foreach($vehicles as $vehicle){
		if($vehicle["vin"] == $config["vin"]){
			$vehicleToUse = $vehicle;
		}
	}
}
$vin = $vehicleToUse["vin"];
$name = $vehicleToUse["nickname"];

debug("getting cardata");
var_dump($id3Login->getRequest("https://mobileapi.apps.emea.vwapps.io/vehicles/".$vin."/status", [], [
	"accept: */*",
	"content-type: application/json",
	"content-version: 1",
	"x-newrelic-id: VgAEWV9QDRAEXFlRAAYPUA==",
	"user-agent: WeConnect/5 CFNetwork/1206 Darwin/20.1.0",
	"accept-language: de-de",
	"Authorization: Bearer ".$id3Login->getAppTokens()["accessToken"],
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


#var_dump($id3Login->authenticatedGetRequest("https://myvw-idk-token-exchanger.apps.emea.vwapps.io/token-exchange?isWcar=true", [], ["Accept: application/json", "Authorization: Bearer " .$id3Login->getCsrf()]));