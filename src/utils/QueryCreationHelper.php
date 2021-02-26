<?php
declare(strict_types=1);

namespace robske_110\utils;

class QueryCreationHelper{
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