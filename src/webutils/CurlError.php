<?php
declare(strict_types=1);

namespace robske_110\webutils;

use RuntimeException;

class CurlError extends RuntimeException{
	public int $curlErrNo;
}