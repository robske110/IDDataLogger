<?php
declare(strict_types=1);

namespace robske_110\vwid\db;

use robske_110\utils\Logger;
use robske_110\utils\QueryCreationHelper;
use robske_110\vwid\Main;
use RuntimeException;

class DBmigrator{
	private DatabaseConnection $db;
	
	public function __construct(DatabaseConnection $db){
		$this->db = $db;
		$this->doAutoMigration();
	}
	
	public function doAutoMigration(){
		try{
			$dbSchemaVersion = $this->db->query(
				"SELECT settingvalue FROM settings WHERE settingKey = 'dbschemaversion'"
			)[0]["settingvalue"];
		}catch(RuntimeException){
			Logger::notice("Could not find settings table, upgrading schema to V1");
			Main::initializeTables($this->db);
			$this->setDBversion("1");
			$dbSchemaVersion = "1";
		}
		if(version_compare($dbSchemaVersion, "1.1") < 0){
			Logger::notice("Upgrading schema to V1.1 (Adding odometer column)");
			$this->db->query("ALTER TABLE carStatus ADD COLUMN odometer integer");
			$this->setDBversion("1.1");
		}
	}
	
	private function setDBversion(string $dbVersion){
		$this->db->query(
			"INSERT INTO settings(settingKey, settingValue) VALUES('dbschemaversion', $dbVersion) ".
			QueryCreationHelper::createUpsert($this->db->getDriver(), "settingKey", ["settingValue"])
		);
	}
}