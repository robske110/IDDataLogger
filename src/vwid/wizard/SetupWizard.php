<?php
declare(strict_types=1);

namespace robske_110\vwid\wizard;

use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\Main;
use RuntimeException;

class SetupWizard extends InteractiveWizard{
	private Main $main;
	
	public function __construct(Main $main){
		$this->main = $main;
		$this->initialSetup();
	}
	
	public function initialSetup(){
		$this->message("Welcome to the ID DataLogger! This setup wizard guides you through the last setup steps!");
		$this->message("A connection to the database has already been established and tables have been initialized.");
		
		$options = getopt("", ["frontend-username:", "frontend-password:", "frontend-apikey:"]);
		if(empty($options["frontend-apikey"])){
			$additional = reset($this->main->getDB()->query("SELECT count(*) FROM authKeys")[0]) > 0;
			$this->message(
				"We can now generate an ".($additional ? "additional" : "").
				" API key for accessing the carStatus and carPicture API. It is required for the iOS widget."
			);
			$generateAPIkey = $this->get(
				"Do you want to generate an ".($additional ? "additional" : "")." API key?",
				$additional ? "N": "Y", ["Y", "N"]
			);
			if($generateAPIkey == "Y"){
				$this->generateAPIkey();
			}
		}else{
			$this->addApiKey($options["frontend-apikey"]);
		}
		
		if(empty($options["frontend-username"]) || empty($options["frontend-password"])){
			$this->message("We can now create a user for the IDView (website with statistics about the car)");
			$generateAPIkey = $this->get("Do you want to create an user?", "Y", ["Y", "N"]);
			if($generateAPIkey == "Y"){
				$this->setupUser();
			}
		}else{
			$this->addUser($options["frontend-username"], $options["frontend-password"]);
		}
		
		$this->message("Perfect! Server will now continue starting...");
	}
	
	private function generateAPIkey(){
		$apiKey = bin2hex(random_bytes(32));
		$this->addApiKey($apiKey);
		$this->message("Successfully generated the API key ".$apiKey."");
		$this->message("Please enter this API key in the apiKey setting at the top of the iOS widget!");
	}
	
	private function addApiKey(string $apiKey){
		if(reset($this->main->getDB()->query("SELECT count(*) FROM authKeys WHERE authkey = '".$apiKey."'")[0]) == 0){
			$this->main->getDB()->query("INSERT INTO authKeys(authKey) VALUES('".$apiKey."')");
		}
	}
	
	private function setupUser(){
		$username = $this->get("Enter the username for the new user");
		$password = $this->get("Now enter the password for the new user");
		if(strlen($username) > 64 && $this->main->getDB()->getDriver() !== "pgsql"){
			throw new RuntimeException("The username cannot be longer than 64 chars!");
		}
		$this->addUser($username, $password);
	}
	
	private function addUser(string $username, string $password){
		$putUser = $this->main->getDB()->prepare(
			"INSERT INTO users(username, hash) VALUES(?, ?)".
			QueryCreationHelper::createUpsert($this->main->getDB()->getDriver(), "username", ["username", "hash"])
		);
		
		$res = $putUser->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
		if($res !== false){
			$this->message("Successfully created the user! Please remember the username and password!");
		}else{
			throw new RuntimeException("Failed to create the user");
		}
	}
}