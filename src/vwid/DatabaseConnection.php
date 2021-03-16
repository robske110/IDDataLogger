<?php
declare(strict_types=1);

namespace robske_110\vwid;

use PDO;
use PDOException;
use PDOStatement;
use robske_110\utils\Logger;
use RuntimeException;

class DatabaseConnection{
	private PDO $connection;
	
	private string $host;
	private string $db;
	private string $username;
	private ?string $password;
	private string $driver;
	
	public function __construct(string $host, string $db, string $username, ?string $password = null, string $driver = "pgsql"){
		$this->host = $host;
		$this->db = $db;
		$this->username = $username;
		$this->password = $password;
		$this->driver = $driver;
		$this->connect();
	}
	
	public function connect(){
		try{
			$this->connection = new PDO(
				$this->driver.":host=".$this->host.";dbname=".$this->db, $this->username, $this->password
			);
		}catch(PDOException $e){
			throw $this->handlePDOexception($e, "Failed to connect to db (check db connection params)");
		}
	}
	
	public function getConnection(): PDO{
		return $this->connection;
	}
	
	public function getDriver(): string{
		return $this->driver;
	}
	
	public function query(string $sql){
		try{
			$res = $this->connection->query($sql);
		}catch(PDOException $e){
			throw $this->handlePDOexception($e, "Query ".$sql." failed");
		}
		return $res->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function prepare(string $sql): PDOStatement{
		try{
			$pdoStatement = $this->connection->prepare($sql);
		}catch(PDOException $e){
			throw $this->handlePDOexception($e, "Preparing query ".$sql." failed");
		}
		return $pdoStatement;
	}
	
	public function queryStatement(string $sql): PDOStatement{
		try{
			$pdoStatement = $this->connection->query($sql);
		}catch(PDOException $e){
			throw $this->handlePDOexception($e, "Running query ".$sql." failed");
		}
		return $pdoStatement;
	}
	
	public function handlePDOexception(PDOException $e, ?string $what = null): RuntimeException{
		Logger::var_dump($e->errorInfo, "errorInfo");
		return new RuntimeException(($what.": " ?? "").$e->getMessage()." [".$e->getCode()."]");
	}
}