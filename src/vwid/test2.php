<?php
declare(strict_types=1);

use robske_110\utils\Logger;
use robske_110\vwid\api\LoginInformation;
use robske_110\vwid\api\WebsiteAPI;

const BASE_DIR = __DIR__."/../../";

require(BASE_DIR."src/Autoloader.php");

function debug($str){
	echo($str.PHP_EOL);
}

Logger::init(false, false);

$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);

$id3Login = new WebsiteAPI(new LoginInformation($config["username"], $config["password"]));

debug("accesstokencar:");
$tokens = json_decode($id3Login->getRequest("https://www.volkswagen.de/app/authproxy/vw-de/tokens", [], ["Accept: application/json", "X-csrf-token: ".$id3Login->getCsrf()]), true);
var_dump($tokens);
$accessTokenCar = $tokens["access_token"];
debug("carlist:");
$cars = json_decode($id3Login->getRequest("https://myvwde.cloud.wholesaleservices.de/api/tbo/cars", [], ["Accept: application/json", "Authorization: Bearer ".$accessTokenCar]));
var_dump($cars);
debug("vehicleimages:");
foreach(json_decode($id3Login->getRequest(
	"https://vehicle-image.apps.emea.vwapps.io/vehicleimages/exterior/".$cars[0]->vin."?viewDirection=side&angle=right",
	[],
	["Accept: application/json", "Authorization: Bearer ".$accessTokenCar]
), true)["images"] as $image){
	var_dump($image);
	if(
		$image["viewDirection"] == ($config["carpic"]["viewDirection"] ?? "front") &&
		$image["angle"] == ($config["carpic"]["viewDirection"] ?? "right")
	){
		$imageUrl = $image["url"];
	}
}

exit;

debug("user:");
$userInfo = json_decode($id3Login->getRequest("https://www.volkswagen.de/app/authproxy/vw-de/user", [], ["Accept: application/json", "X-csrf-token: ".$id3Login->getCsrf()]), true);
var_dump($userInfo);

#https://w1hub-backend-production.apps.emea.vwapps.io/cars is empty ???

debug("accesstokenweconnect:");
$accessTokenWeConnect = json_decode($id3Login->getRequest("https://www.volkswagen.de/app/authproxy/vwag-weconnect/tokens", [], ["Accept: application/json", "X-csrf-token: ".$id3Login->getCsrf()]), true)["access_token"];
debug("accesstokenweconnectexchange:");
$accessTokenWeConnect = $id3Login->getRequest("https://myvw-idk-token-exchanger.apps.emea.vwapps.io/token-exchange?isWcar=true", [], ["Accept: application/json, text/plain", "Authorization: Bearer ".$accessTokenWeConnect]);

debug("fuelstatus:");
var_dump(json_decode($id3Login->getRequest("https://cardata.apps.emea.vwapps.io/vehicles/VIN/fuel/status", [], ["Accept: application/json", "Authorization: Bearer ".$accessTokenWeConnect, "User-Id: ".$userInfo["sub"]]), true));

var_dump($id3Login->getRequest("https://customer-profile.apps.emea.vwapps.io/v1/customers/" . $userInfo["sub"] . "/realCarData", [], [
	"user-agent: okhttp/3.7.0",
	"Accept: application/json",
	"Authorization: Bearer ".$accessTokenCar,
	"Host: customer-profile.apps.emea.vwapps.io",
]));
