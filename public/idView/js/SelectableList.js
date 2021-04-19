class SelectableList{
	constructor(listElement, onSelectFunc){
		this.currSelection = null;
		this.listElement = listElement;
		this.onSelectFunc = onSelectFunc;
		this.initListeners();
	}

	initListeners(){
		for(let preset of this.listElement.children){
			if(!preset.classList.contains("selectablePage")){
				continue;
			}
			let me = this;
			preset.onclick = function(){
				me.select(this)
			}
		}
	}
	
	select(element){
		if(this.currSelection !== element){
			element.children[0].classList.add("active");
			if(this.currSelection !== null){
				this.currSelection.children[0].classList.remove("active");
			}
			this.onSelectFunc(this.currSelection === null ? null : this.currSelection.id, element.id);
			this.currSelection = element;
		}
	}

}