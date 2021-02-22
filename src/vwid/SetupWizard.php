<?php
declare(strict_types=1);

namespace robske_110\vwid;


use http\Exception\RuntimeException;

class SetupWizard{
	private Main $main;
	
	public function __construct(Main $main){
		$this->main = $main;
		$this->initalSetup();
	}
	
	public function initalSetup(){
		$this->message("Welcome to the vwid DataLogger! This setup wizard guides you through the last setup steps!");
		$this->message("A connection to the database has already been established and tables have been initialized.");
		
		$this->message("We can now generate an API key for accessing the carStatus and carPicture API. This is needed for the iOS widget.");
		$generateAPIkey = $this->get("Do you want to generate an API key?", "Y", ["Y", "N"]);
		if($generateAPIkey == "Y"){
			$this->generateAPIkey();
		}
		
		$this->message("We can now setup a user for the IDView (website with statistics about the car)");
		$generateAPIkey = $this->get("Do you want to create an user?", "Y", ["Y", "N"]);
		if($generateAPIkey == "Y"){
			$this->setupUser();
		}
		
		$this->message("Perfect! Server will now continue starting...");
	}
	
	private function generateAPIkey(){
		$apiKey = bin2hex(random_bytes(32));
		$this->main->getDB()->query("INSERT INTO authKeys(key) VALUES('".$apiKey."')");
		$this->message("Successfully generated the API key ".$apiKey."");
		$this->message("Please enter this API key in the apiKey setting at the top of the iOS widget!");
	}
	
	private function setupUser(){
		$password = $this->get("Enter the username for the new user");
		$username = $this->get("Now enter the password for the new user");
		
		if(pg_prepare($this->main->getDB()->getConnection(), "putUser", "INSERT INTO users(username, hash) VALUES($1, $2)") === false){
			throw new RuntimeException("Failed to prepare putUser");
		}
		
		$res = pg_execute($this->main->getDB()->getConnection(), "putUser", [$username, password_hash($password, PASSWORD_DEFAULT)]);
		if($res !== false){
			$this->message("Successfully created the user! Please remember the username and password!");
		}else{
			throw new RuntimeException("Failed to create the user");
		}
	}
	
	private function readLine(): string{
		return trim((string) fgets(STDIN));
	}
	
	private function message(string $message): void{
		echo($message.PHP_EOL);
	}
	
	private function get(string $msg, ?string $default = null, array $options = []): ?string{
		$msg = "> ".$msg;
		
		
		if(!empty($options)){
			$msg .= " (".implode(",", $options).")";
		}
		if($default !== null){
			$msg .= " [".$default."]";
		}
		$msg .= ": ";
		
		echo $msg;
		
		$input = $this->readLine();
		
		return $input === "" ? $default : $input;
	}
}