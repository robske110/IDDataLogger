<?php
declare(strict_types=1);

namespace robske_110\webutils;

use CurlHandle;
use Exception;

class CurlWrapper{
	protected CurlHandle $ch;
	
	public function setCurlOption(int $curlOption, $value){
		if(!curl_setopt($this->ch, $curlOption, $value)){
			throw new Exception("Failed to set curl option ".$curlOption." to ".print_r($value, true));
		}
	}
	
	public function __construct($enableVerbose = false){
		$this->ch = curl_init();
		$this->setCurlOption(CURLOPT_VERBOSE, $enableVerbose);
		$this->setCurlOption(CURLOPT_COOKIEFILE, ""); //enable cookie handling
		$this->setCurlOption(CURLOPT_FOLLOWLOCATION, true);
	}
	
	private static function parseHeaderLine(string $headerLine): ?array{
		$colonPos = strpos($headerLine, ":");
		if($colonPos === false){
			return null;
		}
		$name = substr($headerLine, 0, $colonPos);
		return [$name, substr($headerLine, $colonPos+2)];
	}
	
	protected function onHeaderEntry(string $headerEntryName, string $headerEntryValue){
	
	}
	
	protected function onCookie(string $cookieName, string $cookieContent){
	
	}
	
	private function curlHeader(CurlHandle $ch, string $headerLine){
		$headerEntry = self::parseHeaderLine($headerLine);
		if($headerEntry !== null){
			$this->onHeaderEntry(...$headerEntry);
		}
		if(($setCookiePos = strpos($headerLine, "set-cookie: ")) !== false){
			$posAssign = strpos($headerLine, "=");
			$cookieName = substr($headerLine, strlen("set-cookie: "), $posAssign-strlen("set-cookie: "));
			$posSemicolon = strpos($headerLine, ";");
			$cookieContent = substr($headerLine, $posAssign+1, $posSemicolon-$posAssign-1);
			$this->onCookie($cookieName, $cookieContent);
		}
		return strlen($headerLine);
	}
	
	/**
	 * Performs curl_exec with returntransfer and the curlHeader callbacks on the CURL resource $this->ch
	 *
	 * @return string Returns the result
	 */
	protected function curl_exec(): string{
		$this->setCurlOption(CURLOPT_RETURNTRANSFER, true);
		$this->setCurlOption(CURLOPT_HEADERFUNCTION, [$this, "curlHeader"]);
		$result = curl_exec($this->ch);
		if($result === false){
			if(curl_errno($this->ch)){
				$curlError = new CurlError("A curl request failed: ".curl_error($this->ch)." [".curl_errno($this->ch)."]");
				$curlError->curlErrNo = curl_errno($this->ch);
				throw $curlError;
			}else{
				throw new Exception("A curl request failed with an unknown reason.");
			}
		}
		return $result;
	}
	
	public function getRequest(string $url, array $fields = [], array $header = []){
		debug("Get request to ".$url);
		var_dump($fields);
		curl_setopt($this->ch, CURLOPT_POST, false);
		curl_setopt($this->ch, CURLOPT_URL, $url.HTTPUtils::makeFieldStr($fields));
		#$header[] = "Connection: keep-alive";
		#var_dump($header);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
		return $this->curl_exec();
	}
	
	public function postRequest(string $url, $body, array $header = []){
		debug("Post request to ".$url);
		var_dump($body);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($this->ch, CURLOPT_URL, $url);
		#$header[] = "Connection: keep-alive";
		var_dump($header);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
		return $this->curl_exec();
		
	}
	
	public function getCh(){
		return $this->ch;
	}
}