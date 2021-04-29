<?php
declare(strict_types=1);

namespace robske_110\vwid;

interface CarStatusWrittenUpdateReceiver{
	public function carStatusWrittenUpdate(array $carStatusData);
}