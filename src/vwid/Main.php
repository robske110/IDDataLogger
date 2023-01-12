<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\ErrorUtils;
use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\chargesession\ChargeSessionHandler;
use robske_110\vwid\db\DatabaseConnection;
use robske_110\vwid\db\DBmigrator;
use robske_110\vwid\api\API;
use robske_110\vwid\integrations\ABRP;
use robske_110\vwid\wizard\SetupWizard;

class Main{
	private bool $firstTick = true;
	
	public array $config;
	
	private DatabaseConnection $db;
	
	private CarStatusFetcher $carStatusFetcher;
	private ChargeSessionHandler $chargeSessionHandler;
	
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
		if(!empty($this->config["integrations"]["abrp"]["user-token"])){
			Logger::addOutputFilter($this->config["integrations"]["abrp"]["user-token"]);
		}
		
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
			self::initializeTables($this->db);
			if(($_SERVER['argv'][1] ?? "") != "--no-wizard"){
				new SetupWizard($this);
				$didWizard = true;
			}
		}else{
			new DBmigrator($this->db);
		}
		if(!$didWizard && ($_SERVER['argv'][1] ?? "") === "--wizard"){
			new SetupWizard($this);
		}
		
		API::$VERBOSE = $this->config["logging"]["curl-verbose"] ?? false;
		
		new CarPictureHandler($this->db, $this->config);
		
		$this->chargeSessionHandler = new ChargeSessionHandler($this->db);
		$this->carStatusFetcher = new CarStatusFetcher($this->config);
		$carStatusWriter = new CarStatusWriter($this->db);
		$carStatusWriter->registerUpdateReceiver($this->chargeSessionHandler);
		$this->carStatusFetcher->registerUpdateReceiver($carStatusWriter);

		if(!empty($this->config["integrations"]["abrp"]["user-token"])){
			$abrpIntegration = new ABRP(
				$this->config["integrations"]["abrp"]["user-token"],
				$this->config["integrations"]["abrp"]["api-key"] ?? null,
				$this->chargeSessionHandler
			);
			$carStatusWriter->registerUpdateReceiver($abrpIntegration);
		}
	}
	
	public function getDB(): DatabaseConnection{
		return $this->db;
	}
	
	public static function initializeTables(DatabaseConnection $db){
		Logger::log("Initializing db tables...");
		$sqlFilename = match($db->getDriver()){
			'mysql' => 'db_mysql.sql',
			'pgsql' => 'db.sql'
		};
		$db->getConnection()->exec(file_get_contents(BASE_DIR.$sqlFilename));
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