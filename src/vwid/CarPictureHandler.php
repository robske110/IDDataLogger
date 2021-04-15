<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\api\LoginInformation;
use robske_110\vwid\api\WebsiteAPI;
use RuntimeException;

class CarPictureHandler{
	const PICTURE_LOCATION = BASE_DIR."data/carPic.png";
	
	private DatabaseConnection $db;
	
	private array $config;
	
	public function __construct(DatabaseConnection $db, array $config){
		$this->config = $config;
		@mkdir(BASE_DIR."data/");
		if(!file_exists(BASE_DIR."data/carPic.png")){
			Logger::log("Fetching carPicture (this will take a while...)");
			try{
				$this->fetchCarPicture();
			}catch(RuntimeException $e){
				ErrorUtils::logException($e);
				Logger::warning("Failed to automatically fetch the carPicture! You can substitute data/carPic.png manually.");
				$im = imagecreate(100, 100);
				imagecolorallocate($im, 255, 0, 0);
				imagepng($im, self::PICTURE_LOCATION, 9, PNG_NO_FILTER);
			}
		}
		$this->db = $db;
		$this->writeCarPictureToDB();
	}
	
	public function fetchCarPicture(){
		$websiteAPI = new WebsiteAPI(new LoginInformation($this->config["username"], $this->config["password"]));
		
		$cars = $websiteAPI->apiGetAP("https://myvwde.cloud.wholesaleservices.de/api/tbo/cars");
		$vin = $cars[0]["vin"];
		if(!empty($this->config["vin"])){
			foreach($cars as $car){
				if($car["vin"] === $this->config["vin"]){
					$vin = $car["vin"];
				}
			}
			if($vin !== $this->config["vin"]){
				Logger::var_dump($cars, "cars");
				Logger::warning(
					"Could not find the vehicle with the specified vin ('".$this->config["vin"] ."')!".
					"Will fetch image for default car, please check config and try again by deleting data/carPic.png!"
				);
			}
		}
		$images = $websiteAPI->apiGetAP(
			"https://vehicle-image.apps.emea.vwapps.io/vehicleimages/exterior/".$vin
		)["images"];
		foreach($images as $image){
			if(
				$image["viewDirection"] == ($this->config["viewDirection"] ?? "front") &&
				$image["angle"] == ($this->config["angle"] ?? "right")
			){
				$imageUrl = $image["url"];
			}
		}
		if(!isset($imageUrl)){
			Logger::var_dump($images);
			throw new RuntimeException(
				"Unable to fetch a car picture: Could not find uri for vin: ".
				$vin.", viewDirection: ".$this->config["viewDirection"].", angle: ".$this->config["angle"]
			);
		}
		file_put_contents(self::PICTURE_LOCATION, file_get_contents($imageUrl));
		
		$im = imagecreatefrompng(self::PICTURE_LOCATION);
		if($im === false){
			throw new RuntimeException("Failed to fetch car picture: Failed to download picture. (Check for up to date CA definitions!)");
		}
		imagealphablending($im, false);
		imagesavealpha($im, true);
		
		$cropped = imagecropauto($im, IMG_CROP_SIDES);
		imagedestroy($im);
		if($cropped === false){
			return;
		}
		imagealphablending($cropped, false);
		imagesavealpha($cropped, true);
		if($this->config["carpic"]["flip"] == true){
			imageflip($cropped, IMG_FLIP_HORIZONTAL);
		}
		imagepng($cropped, self::PICTURE_LOCATION, 9, PNG_NO_FILTER);
		imagedestroy($cropped);
		Logger::log("Successfully cropped and saved picture");
	}
	
	public function writeCarPictureToDB(){
		$this->db->query(
			"INSERT INTO carPictures(pictureID, carPicture) VALUES('default', '".base64_encode(file_get_contents(self::PICTURE_LOCATION))."') ".
			QueryCreationHelper::createUpsert($this->db->getDriver(), "pictureID", ["carPicture"])
		);
	}
}