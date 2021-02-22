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

debug("carlist:");
$cars = $id3Login->apiGetAP("https://myvwde.cloud.wholesaleservices.de/api/tbo/cars");
var_dump($cars);
debug("vehicleimages:");
foreach($id3Login->apiGetAP(
	"https://vehicle-image.apps.emea.vwapps.io/vehicleimages/exterior/".$cars[0]["vin"]
)["images"] as $image){
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
