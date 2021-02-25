<?php
declare(strict_types=1);

class DatabaseConnection{
	private PDO $connection;
	
	private string $host;
	private string $db;
	private string $username;
	private ?string $password;
	private string $driver;
	
	public static ?DatabaseConnection $instance = null;
	
	public static function getInstance(){
		if(!self::$instance instanceof DatabaseConnection){
			self::$instance = new DatabaseConnection(
				$_ENV["DB_HOST"], $_ENV["DB_NAME"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"] ?? null,
				$_ENV["DB_DRIVER"] ?? "pgsql"
			);
		}
		return self::$instance;
	}
	
	public function __construct(string $host, string $db, string $username, ?string $password = null, string $driver = "pgsql"){
		$this->host = $host;
		$this->db = $db;
		$this->username = $username;
		$this->password = $password;
		$this->driver = $driver;
		$this->connect();
	}
	
	public function connect(){
		$this->connection = new PDO(
			$this->driver.":host=".$this->host.";dbname=".$this->db, $this->username, $this->password
		);
	}
	
	public function getConnection(): PDO{
		return $this->connection;
	}
	
	public function query(string $sql): array{
		$res = $this->connection->query($sql);
		return $res->fetchAll();
	}
	
	public function queryStatement(string $sql): PDOStatement{
		return $this->connection->query($sql);
	}
	
	public function prepare(string $sql): PDOStatement{
		return $this->connection->prepare($sql);
	}
}