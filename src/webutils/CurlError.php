<?php
declare(strict_types=1);

namespace robske_110\webutils;

use Exception;

class CurlError extends Exception{
	public int $curlErrNo;
}