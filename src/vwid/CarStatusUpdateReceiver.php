<?php
declare(strict_types=1);

namespace robske_110\vwid;

interface CarStatusUpdateReceiver{
	public function carStatusUpdate(array $carStatusData);
}