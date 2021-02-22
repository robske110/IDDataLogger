<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\Logger;

class Main{
	private bool $firstTick = true;
	
	public array $config;
	
	private DatabaseConnection $db;
	
	private CarStatusFetcher $carStatusFetcher;
	private CarStatusWriter $carStatusWriter;
	
	public function __construct(){
		Logger::log("Reading config...");
		$this->config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);
		Logger::addOutputFilter($this->config["password"]);
		
		Logger::log("Connecting to db...");
		$this->db = new DatabaseConnection(
			$this->config["db"]["host"], $this->config["db"]["dbname"], $this->config["db"]["user"], $this->config["db"]["password"] ?? null);
		
		$didWizard = false;
		if(($this->db->query("SELECT to_regclass('public.carStatus')")[0]["to_regclass"] ?? null) !== "carstatus"){
			Logger::log("Initializing db tables...");
			$this->db->query(file_get_contents(BASE_DIR."db.sql"));
			if(($_SERVER['argv'][1] ?? "") != "nowizard"){
				new SetupWizard($this);
				$didWizard = true;
			}
		}
		if(!$didWizard && ($_SERVER['argv'][1] ?? "") == "wizard"){
			new SetupWizard($this);
		}
		
		new CarPictureHandler($this);
		
		$this->carStatusFetcher = new CarStatusFetcher($this);
		$this->carStatusWriter = new CarStatusWriter($this);
	}
	
	public function getDB(){
		return $this->db;
	}
	
	public function getCarStatusWriter(): CarStatusWriter{
		return $this->carStatusWriter;
	}
	
	public function tick(int $tickCnter){
		if($this->firstTick === true){
			Logger::log("Ready!");
			$this->firstTick = false;
		}
		$this->carStatusFetcher->tick($tickCnter);
	}
	
	public function shutdown(){
		Logger::debug(">Closing DataBase connection...");
		$this->db->close();
	}
}