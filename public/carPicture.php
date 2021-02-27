<?php
session_cache_limiter('none');
const YEAR = 60*60*24*365;
header("Cache-control: max-age=".YEAR);
header("Expires: ".(new DateTime("@".(time()+YEAR)))->format(DateTime::RFC1123));
define("ALLOW_KEY_AUTHENTICATION", true);
require "login/loginCheck.php";
require_once "DatabaseConnection.php";

$carPics = DatabaseConnection::getInstance()->query("SELECT carpicture FROM carPictures WHERE pictureID = 'default'");

$pic = base64_decode($carPics[0]["carpicture"]);
header("Content-Type: image/png");
header("Content-Length: ".strlen($pic));
echo($pic);