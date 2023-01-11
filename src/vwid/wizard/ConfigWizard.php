<?php
declare(strict_types=1);

namespace robske_110\vwid\wizard;

class ConfigWizard extends InteractiveWizard{
	public function __construct(){
		if(isset(getopt("", ["secure"])["secure"])){
			$ini = parse_ini_file(BASE_DIR.".env", false, INI_SCANNER_TYPED);
			if(isset($ini["FORCE_ALLOW_HTTP"])){
				unset($ini["FORCE_ALLOW_HTTP"]);
				if(!isset(getopt("", ["quiet"])["quiet"])){
					$this->message("Successfully removed FORCE_ALLOW_HTTP from .env. Please make sure to replace the .env file!");
				}
			}
			$this->writeDotEnv($ini);
			exit;
		}
		if(isset(getopt("", ["use-env"])["use-env"])){
			exit($this->createConfigFromEnvironment() ? 0 : 1);
		}
		if(isset(getopt("", ["setup-abrp"])["setup-abrp"])){
			if(!file_exists(BASE_DIR."config/config.json")){
				$this->interactiveDBconfig();
			}
			$this->interactiveABRPconfig();
			exit;
		}
		$this->interactiveDBconfig();
	}
	
	public function createConfigFromEnvironment(): bool{
		$options = getopt("", ["fill-defaults", "quiet"]);
		$quiet = isset($options["quiet"]);
		if(isset($options["fill-defaults"])){
			$config = json_decode(file_get_contents(BASE_DIR."config/config.example.json"), true);
		}else{
			if(file_exists(BASE_DIR."config/config.json")){
				$config = json_decode(file_get_contents(BASE_DIR."config/config.json"), true);
			}else{
				if(!$quiet) $this->message("No config.json found! To create from config.example.json specify --fill-defaults!");
				return false;
			}
		}
		$_ENV = getenv(); //For configurations where the $_ENV superglobal is disabled, manually populate it.
		$config = $this->readEnv($config);
		file_put_contents(BASE_DIR."config/config.json", json_encode($config, JSON_PRETTY_PRINT));
		if(!$quiet) $this->message("Successfully created/updated config.json from the environment variables");
		return true;
	}
	
	const CONFIG_BOOLEANS = [
		"IDDATALOGGER_CARPIC_FLIP",
		"IDDATALOGGER_LOGGING_DEBUG_ENABLE",
		"IDDATALOGGER_LOGGING_FILE_ENABLE"
	];
	
	const CONFIG_INTEGERS = [
		"IDDATALOGGER_BASE_UPDATERATE",
		"IDDATALOGGER_INCREASED_UPDATERATE"
	];
	
	public function readEnv(array $config, string $path = ""): array{
		foreach($config as $key => $value){
			if(is_array($value)){
				$config[$key] = $this->readEnv($value, $path.strtoupper(str_replace("-", "_", $key))."_");
				continue;
			}
			$envName = "IDDATALOGGER_".$path.strtoupper(str_replace("-", "_", $key));
			if(!empty($_ENV[$envName])){
				if(in_array($envName, self::CONFIG_BOOLEANS)){
					$config[$key] = $_ENV[$envName] == "true";
				}elseif(in_array($envName, self::CONFIG_INTEGERS)){
					$config[$key] = (int) $_ENV[$envName];
				}else{
					$config[$key] = $_ENV[$envName];
				}
			}
		}
		return $config;
	}
	
	public function interactiveDBconfig(){
		$this->message("Welcome to the config wizard for the ID DataLogger!");
		$this->message("This wizard makes it easy to enter the VW account login and database connection information into the respective config files.");
		
		$this->message("You will now be asked for your VW account login information");
		$idUsername = $this->get("What is the username of your VW ID account?");
		$idPassword = $this->get("What is the password of your VW ID account?");
		
		$options = getopt("", ["host:", "dbname:", "user:", "password:", "driver:", "allow-insecure-http", "quiet"]);
		if(empty($options)){
			$this->message("You will now be asked for your database connection information");
		}
		if(isset($options["host"])){
			$hostname = $options["host"];
		}else{
			$hostname = $this->get("What is the hostname of the database server?", "localhost");
		}
		if(isset($options["dbname"])){
			$dbname = $options["dbname"];
		}else{
			$dbname = $this->get("What is the name of the database for the ID DataLogger?");
		}
		if(isset($options["user"])){
			$username = $options["user"];
		}else{
			$username = $this->get("What is the username of the user to connect to the database server?");
		}
		if(isset($options["password"])){
			$password = $options["password"];
		}else{
			$password = $this->get("What is the password of the user to connect to the database server? (If no password is needed leave this blank or enter null.)", "null");
		}
		if($password == "null" || empty($password)){
			$password = null;
		}
		if(isset($options["driver"])){
			$driver = $options["driver"];
		}else{
			$driver = $this->get("What is the driver we should use for connecting to the database server?", "pgsql");
		}
		
		if(file_exists(BASE_DIR."config/config.json")){
			if($this->get("A config.json already exists. Do you want to overwrite it with the defaults from config.example.json?", "N", ["Y", "N"]) != "Y"){
				if($this->get("Do you want to update your existing config with the parameters you entered in this wizard? (Answering N will exit this Wizard)", "Y", ["Y", "N"]) == "Y"){
					$this->writeJsonConfig($idUsername, $idPassword, $hostname, $dbname, $username, $password, $driver, "config.json");
				}else{
					exit;
				}
			}else{
				$this->writeJsonConfig($idUsername, $idPassword, $hostname, $dbname, $username, $password, $driver);
			}
		}else{
			$this->writeJsonConfig($idUsername, $idPassword, $hostname, $dbname, $username, $password, $driver);
		}
		
		$ini = parse_ini_file(BASE_DIR.".env.example", false, INI_SCANNER_TYPED);
		if(isset($options["allow-insecure-http"])){
			$this->message(<<<WARN
WARNING: FORCE_ALLOW_HTTP is enabled. This will prevent the frontend from blocking and redirecting http requests.
Make sure to disable this should you eventually enable https and expose this project to the internet!
To disable run ./config-wizard.sh --secure and make sure to replace the old .env file with the newly generated one!
WARN);
			$ini["FORCE_ALLOW_HTTP"] = true;
		}
		
		$ini["DB_HOST"] = $hostname;
		$ini["DB_NAME"] = $dbname;
		$ini["DB_USER"] = $username;
		$ini["DB_PASSWORD"] = $password;
		$ini["DB_DRIVER"] = $driver;
		
		$this->writeDotEnv($ini);
		
		if(!isset($options["quiet"])){
			$this->message("Perfect! The configuration has been written to config/config.json and the .env file.");
			$this->message(<<<INFO
You can now copy the contents of the public/ folder to the appropriate place in your webroot (recommended: webroot/some-folder/)
and then copy the .env file outside your webroot. Please refer to the installation documentation for other ways of setting the
environment variables or placing the content of public in another level of your webroot.
INFO);
		}
	}

	public function interactiveABRPconfig(){
		$this->message("Welcome to the config wizard for enabling the ABRP integration");
		$this->message("You need to get a token for the generic data source from within the ABRP app.");
		$this->message("See the wiki page \"ABRP integration\" for details!");

		$abrpUserToken = $this->get("What is your abrp user token?");

		if(file_exists(BASE_DIR."config/config.json")){
			$this->writeJsonConfigABRP($abrpUserToken, "config.json");
		}else{
			$this->writeJsonConfigABRP($abrpUserToken);
		}

		$this->message("Perfect! The abrp user token has been written to config/config.json. You need to restart IDDataLogger now.");
	}
	
	private function writeDotEnv(array $iniValues){
		$newIni = "";
		foreach($iniValues as $key => $value){
			if(is_string($value)){
				$value = "\"".$value."\"";
			}
			if($value === null){
				$value = "null";
			}
			if(is_bool($value)){
				$value = $value ? "true" : "false";
			}
			$newIni .= $key."=".$value."\n";
		}
		file_put_contents(BASE_DIR.".env", $newIni);
	}
	
	private function writeJsonConfig(
		?string $idUsername, ?string $idPassword, string $hostname, string $dbname, string $username, ?string $password,
		string $driver, string $filename = "config.example.json"
	): array{
		$config = json_decode(file_get_contents(BASE_DIR."config/".$filename), true);
		if(!empty($idUsername)){
			$config["username"] = $idUsername;
		}
		if(!empty($idPassword)){
			$config["password"] = $idPassword;
		}
		$config["db"]["host"] = $hostname;
		$config["db"]["dbname"] = $dbname;
		$config["db"]["user"] = $username;
		$config["db"]["password"] = $password;
		$config["db"]["driver"] = $driver;
		
		file_put_contents(BASE_DIR."config/config.json", json_encode($config, JSON_PRETTY_PRINT));
		return $config;
	}

	private function writeJsonConfigABRP(
		string $abrpUserToken, string $filename = "config.example.json"
	): array{
		$config = json_decode(file_get_contents(BASE_DIR."config/".$filename), true);

		$config["integrations"]["abrp"]["user-token"] = $abrpUserToken;

		file_put_contents(BASE_DIR."config/config.json", json_encode($config, JSON_PRETTY_PRINT));
		return $config;
	}
}