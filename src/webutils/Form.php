<?php
declare(strict_types=1);

namespace robske_110\webutils;

use DOMElement;

class Form{
	private DOMElement $formElement;
	
	public function __construct(DOMElement $ele){
		$this->formElement = $ele;
	}
	
	public function getAttribute(string $name): string{
		return $this->formElement->attributes->getNamedItem($name)->textContent;
	}
	
	public function getHiddenFields(): array{
		$fields = [];
		foreach($this->formElement->childNodes as $childNode){
			if($childNode->nodeName == "input" && $childNode->attributes->getNamedItem("type")->textContent == "hidden"){
				$fields[$childNode->attributes->getNamedItem("name")->textContent] = $childNode->attributes->getNamedItem("value")->textContent;
			}
		}
		return $fields;
	}
}