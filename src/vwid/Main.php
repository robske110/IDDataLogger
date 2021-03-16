<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\chargesession\ChargeSessionHandler;
use robske_110\vwid\db\DatabaseConnection;
use robske_110\vwid\wizard\SetupWizard;

class Main{
	private bool $firstTick = true;
	
	public array $config;
	
	private DatabaseConnection $db;
	
	private CarStatusFetcher $carStatusFetcher;
	private CarStatusWriter $carStatusWriter;
	private ChargeSessionHandler $chargeSessionHandler;
	
	public function __construct(){
		Logger::log("Reading config...");
		$this->config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);
		Logger::addOutputFilter($this->config["password"]);
		
		Logger::log("Connecting to db...");
		$this->db = new DatabaseConnection(
			$this->config["db"]["host"], $this->config["db"]["dbname"],
			$this->config["db"]["user"], $this->config["db"]["password"] ?? null,
			$this->config["db"]["driver"] ?? "pgsql"
		);
		QueryCreationHelper::initDefaults($this->config["db"]["driver"] ?? "pgsql");
		
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
			if(($_SERVER['argv'][1] ?? "") != "nowizard"){
				new SetupWizard($this);
				$didWizard = true;
			}
		}
		if(!$didWizard && ($_SERVER['argv'][1] ?? "") === "wizard"){
			new SetupWizard($this);
		}
		
		new CarPictureHandler($this);
		
		$this->carStatusFetcher = new CarStatusFetcher($this);
		$this->carStatusWriter = new CarStatusWriter($this);
		$this->chargeSessionHandler = new ChargeSessionHandler($this->db);
	}
	
	public function getDB(){
		return $this->db;
	}
	
	public function pushCarStatus(array $carStatusData){
		$this->carStatusWriter->writeCarStatus($carStatusData);
		$this->chargeSessionHandler->processCarStatus($carStatusData);
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