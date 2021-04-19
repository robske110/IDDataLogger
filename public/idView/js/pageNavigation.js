const pageList = new SelectableList(document.querySelector("#pagenav"), function(oldid, newid){
	if(oldid !== null){
		let oldPage = document.querySelector("#"+oldid+"Page");
		if(oldPage === null){
			alert("Could not find #"+oldid+"Page");
		}else{
			oldPage.classList.add("hidden");
		}
	}
	let newPage = document.querySelector("#"+newid+"Page");
	if(newPage === null){
		alert("Could not find #"+newid+"Page");
	}else{
		newPage.classList.remove("hidden");
		newPage.focus(); //fixes some scrolling issues on iOS
	}
});
pageList.select(document.querySelector("#IDView"));