<?php
declare(strict_types=1);

namespace robske_110\vwid\wizard;

class InteractiveWizard{
	protected function readLine(): string{
		return trim((string) fgets(STDIN));
	}
	
	protected function message(string $message): void{
		echo($message.PHP_EOL);
	}
	
	protected function get(string $msg, ?string $default = null, array $options = []): ?string{
		$msg = "> ".$msg;
		
		
		if(!empty($options)){
			$msg .= " (".implode(",", $options).")";
		}
		if($default !== null){
			$msg .= "\n[".$default."]";
		}
		$msg .= ": ";
		
		echo $msg;
		
		$input = $this->readLine();
		
		return $input === "" ? $default : $input;
	}
}