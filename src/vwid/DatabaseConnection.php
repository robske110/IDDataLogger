<?php
declare(strict_types=1);

namespace robske_110\vwid;

use robske_110\utils\Logger;

class DatabaseConnection{
	/** @var resource */
	private $connection;
	
	private string $host;
	private string $db;
	private string $username;
	private ?string $password;
	
	public function __construct(string $host, string $db, string $username, ?string $password = null){
		$this->host = $host;
		$this->db = $db;
		$this->username = $username;
		$this->password = $password;
		$this->connect();
	}
	
	public function connect(){
		$this->connection = pg_connect(
			"host=".$this->host." dbname=".$this->db.
			" user=".$this->username.($this->password !== null ? " password=".$this->password : "")
		);
		if($this->connection === false){
			throw new \Exception("Failed to connect to db!");
		}
	}
	
	/**
	 * @return resource
	 */
	public function getConnection(){
		return $this->connection;
	}
	
	public function query(string $sql){
		$res = pg_query($this->connection, $sql);
		if($res === false){
			throw new \Exception("Query ".$sql." failed");
		}
		return pg_fetch_all($res);
	}
	
	public function close(){
		pg_close($this->connection);
	}
}