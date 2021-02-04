<?php
declare(strict_types=1);

namespace robske_110\webutils;

final class HTTPUtils{
	public static final function makeFieldStr(array $fields): string{
		if(empty($fields)){
			return "";
		}
		$fieldStr = "?";
		foreach($fields as $fieldName => $fieldValue){
			$fieldStr.= $fieldName."=".$fieldValue."&";
		}
		return $fieldStr;
	}
}