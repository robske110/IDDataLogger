<?php
declare(strict_types=1);

namespace robske_110\utils;

abstract class Logger{
	private static bool $debugEnabled;
	private static $debugFile;
	private static bool $debugFileEnabled;
	private static bool $outputEnabled;
	
	private static array $outputFilter = [];
    
	const LOG_LVL_INFO = 0;
	const LOG_LVL_NOTICE = 1;
	const LOG_LVL_WARNING = 2;
	const LOG_LVL_CRITICAL = 3;
	const LOG_LVL_EMERGENCY = 4;
	const LOG_LVL_DEBUG = 5;
    
	const DEBUG_TYPE_IMPORTED = 0;
	const DEBUG_TYPE_NORMAL = 1;
    
	public static function init(bool $debugEnabled = true, bool $debugFileEnabled = false, string $directory = __DIR__."/debug/"){
		self::$debugEnabled = $debugEnabled;
		self::$debugFileEnabled = $debugFileEnabled;
		self::$outputEnabled = true;
		if($debugFileEnabled){
			@mkdir($directory);
			$filename = $directory."Debug".date("d.m.Y_H:i:s", time()).".log";
			self::$debugFile = fopen($filename,'w+');
			if(!self::$debugFile){
				self::$debugFileEnabled = false;
				self::warning("Failed to create/open '".$filename."' for writing! Writing debug to file is disabled!");
			}
		}
	}
	
	public static function close(){
		if(self::$debugFileEnabled){
			fclose(self::$debugFile);
		}
	}
	
	public static function addOutputFilter(string $outputFilter){
		self::$outputFilter[] = $outputFilter;
	}
	
	public static function setOutputEnabled(bool $enabled = true){
		self::$outputEnabled = $enabled;
	}
	
	private static function filterString(string $str): string{
		foreach(self::$outputFilter as $outputFilter){
			$str = str_replace($outputFilter, "**REMOVED**", $str);
		}
		return $str;
	}
	
	public static function log(string $msg, int $logLvl = self::LOG_LVL_INFO, bool $lBafter = true, bool $lBbefore = false, bool $exportDebug = true){
		$time = gettimeofday();
		$time['sec'] = $time['sec'] - $time['minuteswest'] * 60;
		$currTime = \DateTime::createFromFormat('U.u',implode(".", array_slice($time, 0, 2)))->format("H:i:s.u ");
		if($msg[0] === "\r"){
			$p = "\r";
			$msg[0] = "";
		}else{
			$p = "";
		}
		if($lBbefore){
			$p .= "\n";
		}
		$p .= "\e[97m";
		$p .= $currTime;
		$p .= "\e[0m";
		$r = "\e[0m";
		if($lBafter){
			$r .= "\n";
		}
		switch($logLvl){
			case self::LOG_LVL_INFO: $lvl = "\e[96m[INFO] "; break;
			case self::LOG_LVL_NOTICE: $lvl = "\e[93m[NOTICE] "; break;
			case self::LOG_LVL_WARNING: $lvl = "\e[91m[WARNING] "; break;
			case self::LOG_LVL_CRITICAL: $lvl = "\e[31m[CRITICAL] "; break;
			case self::LOG_LVL_EMERGENCY: $lvl = "\e[41m\e[37m[EMERGENCY] "; break;
			case self::LOG_LVL_DEBUG: $lvl = "[DEBUG] "; break;
		}
		if(self::$outputEnabled){
			$msg = self::filterString($msg);
			echo($p.$lvl.$msg.$r);
		}
		if($exportDebug){
			self::debug($p.$lvl.$msg.$r, self::DEBUG_TYPE_IMPORTED);
		}
		flush();
	}
    
	public static function notice(string $msg){
		self::log($msg, self::LOG_LVL_NOTICE);
	}
	
	public static function warning(string $msg){
		self::log($msg, self::LOG_LVL_WARNING);
	}
	public static function critical(string $msg){
		self::log($msg, self::LOG_LVL_CRITICAL);
	}
	public static function emergency(string $msg){
		self::log($msg, self::LOG_LVL_EMERGENCY);
	}
	
	public static function debug(string $msg, int $debugType = self::DEBUG_TYPE_NORMAL){
		if(self::$debugEnabled){
			if($debugType !== self::DEBUG_TYPE_IMPORTED){
				self::log($msg, self::LOG_LVL_DEBUG);
				return;
			}
			if(self::$debugFileEnabled){
				$msg = self::filterString($msg);
				fwrite(self::$debugFile, $msg);
			}
		}
	}
	
	public static function var_dump($data, ?string $name = null, bool $return = false): string{
		ob_start();
		var_dump($data);
		$str = ob_get_clean();
		if(self::$debugEnabled && !$return){
			if($name !== null){
				self::debug($name.":");
			}
			echo(self::filterString($str));
			self::debug($str, self::DEBUG_TYPE_IMPORTED);
		}
		return $str;
	}
}