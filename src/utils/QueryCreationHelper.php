<?php
declare(strict_types=1);

namespace robske_110\utils;

class QueryCreationHelper{
	private static string $defaultDriver = "";
	
	public static function initDefaults(string $defaultDriver){
		self::$defaultDriver = $defaultDriver;
	}
	
	public static function createInsert(string $tableName, array $columns, bool $doUpsert = true, ?string $driver = null, ?string $primaryKey = null){
		$driver = $driver ?? self::$defaultDriver;
		$query = "INSERT INTO ".$tableName."(";
		foreach($columns as $key){
			$query .= $key.", ";
		}
		$query = substr($query, 0, strlen($query)-2);
		$query .= ") VALUES(";
		for($i = 1; $i < count($columns); ++$i){
			$query .= "?, ";
		}
		$query .= "?) ";
		if($doUpsert){
			$query .= QueryCreationHelper::createUpsert($driver, $primaryKey ?? $columns[0], $columns);
		}
		return $query;
	}
	
	public static function createUpsert(string $driver, string $primaryKey, array $columns): string{
		switch($driver){
			case "pgsql":
				return self::createPostgresUpsert($primaryKey, $columns);
			default:
				Logger::warning("Creating an upsert statement for a not explicitly supported db driver!");
			case "mysql":
				return self::createMySQLUpsert($primaryKey, $columns);
		}
	}
	
	public static function createPostgresUpsert(string $primaryKey, array $columns): string{
		$query = "ON CONFLICT (".$primaryKey.") DO UPDATE SET ";
		foreach($columns as $column){
			$query .= $column." = excluded.".$column.", ";
		}
		return substr($query, 0, strlen($query)-2);
	}
	
	public static function createMySQLUpsert(string $primaryKey, array $columns): string{
		$query = "ON DUPLICATE KEY UPDATE ";
		foreach($columns as $column){
			$query .= $column." = VALUES(".$column."), ";
		}
		return substr($query, 0, strlen($query)-2);
	}
}