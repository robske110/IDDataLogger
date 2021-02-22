<?php
$start = microtime(true);
ini_set("session.gc_maxlifetime", 7*24*60*60);
ini_set("session.cookie_lifetime", 7*24*60*60);

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
	echo("WRONG_REQUEST_METHOD");
	exit;
}

require_once __DIR__."/../env.php";
if((empty($_SERVER['HTTPS']) || ('on' !== $_SERVER['HTTPS'])) && (!isset($_ENV["FORCE_ALLOW_HTTP"]) || $_ENV["FORCE_ALLOW_HTTP"] !== true)){
	echo("HTTP not allowed!");
	exit;
}

if(!isset($_COOKIE['PHPSESSID']) || empty($_COOKIE['PHPSESSID']) || !is_string($_COOKIE['PHPSESSID'])){
	echo("Authentication failure!");
	exit;
}

if(!isset($_POST['username']) || empty($_POST['username']) || !is_string($_POST['username'])){
	echo("Authentication failure!");
	exit;
}
if(!isset($_POST['passwd']) || empty($_POST['passwd']) || !is_string($_POST['passwd'])){
	echo("Authentication failure!");
	exit;
}

require_once __DIR__."/../env.php";

$username = $_POST['username'];
$password = $_POST['passwd'];

$db = pg_connect("host=".$_ENV["DB_HOST"]." dbname=".$_ENV["DB_NAME"]." user=".$_ENV["DB_USER"].(isset($_ENV["DB_PASSWORD"]) ? " password=".$_ENV["DB_PASSWORD"] : ""));

if(pg_prepare($db, "getUser", "SELECT * FROM users WHERE username = $1") === false){
	authFail();
}

$res = pg_execute($db, "getUser", [$username]);
	
$users = pg_fetch_all($res);

if(!isset($users[0])){
	authFail();
}
$hash = $users[0]["hash"];

if(password_verify($password, $hash)){
    if(password_needs_rehash($hash, PASSWORD_DEFAULT)){
        $newHash = password_hash($password, PASSWORD_DEFAULT);
		
		if(pg_prepare($db, "putUser", "INSERT INTO users(username, hash) VALUES($1, $2)") === false){
			exit;
		}

		$res = pg_execute($db, "putUser", [$username, $newHash]);
    }
	session_start();
	$_SESSION['loggedin'] = true;
	echo("success");
}
authFail();
function authFail(){
	global $start;
	echo("Authentication failure!");
	$took = microtime(true) - $start;
	$wait = 0.1-$took;
	usleep($wait*1000000);
	exit;
}