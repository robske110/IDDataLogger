<?php
declare(strict_types=1);

namespace robske_110\utils;

class Uuid{
	public static function randomUUID(): string{
		$data = bin2hex(random_bytes(16));
		return sprintf('%08s-%04s-4%03s-%04x-%012s',
			substr($data, 0, 12),
			substr($data, 8, 4),
			substr($data, 13, 3),
			hexdec(substr($data, 16, 4)) & 0x3FFF | 0x8000,
			substr($data, 20, 12)
		);
	}
}