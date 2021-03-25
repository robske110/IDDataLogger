<?php
declare(strict_types=1);

namespace robske_110\vwid\wizard;

use RuntimeException;

class InteractiveWizard{
	protected function readLine(): string{
		return trim((string) fgets(STDIN));
	}
	
	protected function message(string $message): void{
		echo($message.PHP_EOL);
	}
	
	protected function get(string $msg, ?string $default = null, array $options = []): ?string{
		$out = "> ".$msg;
		
		
		if(!empty($options)){
			$out .= " (".implode(",", $options).")";
		}
		if($default !== null){
			$out .= "\n[".$default."]";
		}
		$out .= ": ";
		
		echo $out;
		
		$input = $this->readLine();
		if(!empty($options) && $input !== ""){
			if(!in_array($input, $options, true)){
				$this->message("Please answer with one of the following options (case-sensitive!): ".implode(",", $options));
				$this->get($msg, $default, $options);
			}
		}
		
		return $input === "" ? $default : $input;
	}
}