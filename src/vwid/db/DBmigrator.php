<?php
declare(strict_types=1);

namespace robske_110\vwid\db;

use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\Main;
use RuntimeException;

class DBmigrator{
	private DatabaseConnection $db;
	private ?string $dbSchemaVersion;
	
	public function __construct(DatabaseConnection $db, bool $initialStart = false){
		$this->db = $db;
		if(!$initialStart){
			$this->doAutoMigration();
		}else{
			$this->setDBversion("1.1");
			Logger::log("Successfully initialized database with schema version V".$this->dbSchemaVersion);
		}
	}
	
	public function doAutoMigration(){
		try{
			$this->dbSchemaVersion = $this->db->query(
				"SELECT settingvalue FROM settings WHERE settingKey = 'dbschemaversion'"
			)[0]["settingvalue"] ?? null;
		}catch(RuntimeException){
			Logger::notice("Could not find settings table, upgrading schema to V1");
			Main::initializeTables($this->db);
			$this->setDBversion("1");
		}
		$startupDbSchemaVersion = $this->dbSchemaVersion;
		// Old versions of IDDataLogger (up to 4e44ce8) wrote the V1 DB schema, but didn't write a dbschemaversion.
		if($this->dbSchemaVersion === null){
			Logger::warning(
				"Could not read dbSchemaVersion, but settings table is present. Ignoring and setting to V1."
			);
			$this->setDBversion("1");
		}
		if(version_compare($this->dbSchemaVersion, "1.1") < 0){
			Logger::notice("Upgrading schema to V1.1 (Adding odometer column)");
			$this->db->query("ALTER TABLE carStatus ADD COLUMN odometer integer");
			$this->setDBversion("1.1");
		}
		if($startupDbSchemaVersion !== $this->dbSchemaVersion){
			Logger::log(
				"Successfully upgraded from database schema version ".
				($startupDbSchemaVersion ?? "unknown")." to V".$this->dbSchemaVersion
			);
		}
	}
	
	private function setDBversion(string $dbVersion){
		$this->db->query(
			"INSERT INTO settings(settingKey, settingValue) VALUES('dbschemaversion', $dbVersion) ".
			QueryCreationHelper::createUpsert($this->db->getDriver(), "settingKey", ["settingValue"])
		);
		$this->dbSchemaVersion = $dbVersion;
	}
}