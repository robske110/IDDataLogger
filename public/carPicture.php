<?php
session_cache_limiter('none');
const YEAR = 60*60*24*365;
header("Cache-control: max-age=".YEAR);
header("Expires: ".(new DateTime("@".(time()+YEAR)))->format(DateTime::RFC1123));
define("ALLOW_KEY_AUTHENTICATION", true);
require "login/loginCheck.php";

$inst = pg_connect("host=".$_ENV["DB_HOST"]." dbname=".$_ENV["DB_NAME"]." user=".$_ENV["DB_USER"].(isset($_ENV["DB_PASSWORD"]) ? " password=".$_ENV["DB_PASSWORD"] : ""));

$carPicRes = pg_query($inst, "SELECT carPicture FROM carPictures WHERE id = 'default'");

$pic = base64_decode(pg_fetch_row($carPicRes)[0]);
header("Content-Type: image/png");
header("Content-Length: ".strlen($pic));
echo($pic);