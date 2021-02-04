<?php
declare(strict_types=1);

namespace robske_110\vwid;

class LoginInformation{
	public string $username;
	public string $password;
	
	public function __construct(string $username, string $password){
		$this->username = $username;
		$this->password = $password;
	}
}