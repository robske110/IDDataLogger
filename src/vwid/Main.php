<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\vwid\api\API;
use robske_110\vwid\wizard\SetupWizard;

class Main{
	private bool $firstTick = true;
	
	public array $config;
	
	private DatabaseConnection $db;
	
	private CarStatusFetcher $carStatusFetcher;
	private CarStatusWriter $carStatusWriter;
	
	public function __construct(){
		Logger::log("Reading config...");
		try{
			$this->config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException $exception){
			ErrorUtils::logException($exception);
			Logger::warning("Unable to parse config.json! Most likely invalid format.");
			forceShutdown();
		}
		Logger::addOutputFilter($this->config["password"]);
		
		Logger::log("Connecting to db...");
		$this->db = new DatabaseConnection(
			$this->config["db"]["host"], $this->config["db"]["dbname"],
			$this->config["db"]["user"], $this->config["db"]["password"] ?? null,
			$this->config["db"]["driver"] ?? "pgsql"
		);
		
		$didWizard = false;
		if(strtolower($this->db->query(
			"SELECT table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'carstatus' OR TABLE_NAME = 'carStatus'"
			)[0]["table_name"] ?? "") !== "carstatus"
		){
			Logger::log("Initializing db tables...");
			$sqlFilename = match($this->db->getDriver()){
				'mysql' => 'db_mysql.sql',
				'pgsql' => 'db.sql'
			};
			$this->db->getConnection()->exec(file_get_contents(BASE_DIR.$sqlFilename));
			if(($_SERVER['argv'][1] ?? "") != "--no-wizard"){
				new SetupWizard($this);
				$didWizard = true;
			}
		}
		if(!$didWizard && ($_SERVER['argv'][1] ?? "") === "--wizard"){
			new SetupWizard($this);
		}
		
		API::$VERBOSE = $this->config["logging"]["curl-verbose"] ?? false;
		
		new CarPictureHandler($this->db, $this->config);
		
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
	}
}