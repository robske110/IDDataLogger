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
		//we are loading chargingOverview here, because loading it without displaying it causes Chart.js to break on Firefox
		//todo: should we limit this just to firefox, since it introduces an unnecessary loading time
		if(newid == "chargingOverview" && (newPage.src === undefined || !newPage.src.includes("chargingOverview.html"))){
			newPage.src = "chargingOverview.html";
			newPage.onload = function(){
				observer.observe(document.querySelector("#chargingOverviewPage").contentWindow.document.body, { attributes: true, childList: true, subtree: true });
			};
		}
		newPage.focus(); //fixes some scrolling issues on iOS
	}
});

pageList.select(document.querySelector("#IDView"));

const observer = new MutationObserver(function(mutationsList, observer){
	const chargingOverview = document.querySelector("#chargingOverviewPage");
	chargingOverview.style.height = chargingOverview.contentDocument.body.scrollHeight;
	console.log("found mutation!");
});