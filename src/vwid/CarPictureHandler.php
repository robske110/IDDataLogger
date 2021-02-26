<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\Logger;
use robske_110\vwid\api\LoginInformation;
use robske_110\vwid\api\WebsiteAPI;

class CarPictureHandler{
	const PICTURE_LOCATION = BASE_DIR."data/carPic.png";
	
	private $main;
	
	public function __construct(Main $main){
		$this->main = $main;
		@mkdir(BASE_DIR."data/");
		if(!file_exists(BASE_DIR."data/carPic.png")){
			Logger::log("Fetching carPicture (this will take a while...)");
			$this->fetchCarPicture();
		}
		$this->main->getDB()->query(
			"ON CONFLICT (id) DO UPDATE SET carPicture = excluded.carPicture"
			"INSERT INTO carPictures(pictureID, carPicture) VALUES('default', '".base64_encode(file_get_contents(self::PICTURE_LOCATION))."') ".
		);
	}
	
	public function fetchCarPicture(){
		$config = $this->main->config;
		$websiteAPI = new WebsiteAPI(new LoginInformation($config["username"], $config["password"]));
		
		$cars = $websiteAPI->apiGetAP("https://myvwde.cloud.wholesaleservices.de/api/tbo/cars");
		foreach($websiteAPI->apiGetAP(
			"https://vehicle-image.apps.emea.vwapps.io/vehicleimages/exterior/".$cars[0]["vin"]
		)["images"] as $image){
			if(
				$image["viewDirection"] == ($config["carpic"]["viewDirection"] ?? "front") &&
				$image["angle"] == ($config["carpic"]["angle"] ?? "right")
			){
				$imageUrl = $image["url"];
			}
		}
		if(!isset($imageUrl)){
			throw new \RuntimeException("Unable to fetch a car picture");
		}
		file_put_contents(self::PICTURE_LOCATION, file_get_contents($imageUrl));
		
		$im = imagecreatefrompng(self::PICTURE_LOCATION);
		imagealphablending($im, false);
		imagesavealpha($im, true);
		
		$cropped = imagecropauto($im, IMG_CROP_SIDES);
		if($cropped === false){
			return;
		}
		imagedestroy($im);
		imagealphablending($cropped, false);
		imagesavealpha($cropped, true);
		if($config["carpic"]["flip"] == true){
			imageflip($cropped, IMG_FLIP_HORIZONTAL);
		}
		imagepng($cropped, self::PICTURE_LOCATION, 9, PNG_NO_FILTER);
		imagedestroy($cropped);
		Logger::log("Successfully cropped and saved picture");
	}
}