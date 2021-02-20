flatpickr.localize(flatpickr.l10ns.de);
let timetravelPicker = flatpickr("#timetravel", {dateFormat: "d.m.Y H:i", onChange: timetravelUser, enableTime: true, time_24hr: true});
flatpickr("#graphDateRange", {mode: "range", dateFormat: "d.m.Y"});
	
let chargePower = new AnimatedValue(document.getElementById("chargePower"), 0, "kW", 1, 10);
let chargeKMPH = new AnimatedValue(document.getElementById("chargeKMPH"), 0, "km/h");
let range = new AnimatedValue(document.getElementById("range"), 0, "km");
let targetTemp = new AnimatedValue(document.getElementById("hvactargettemp"), 0, "°C", 1, 10);
let soc = new SOCDoughnutValue(document.getElementById("soc"), 0, 100, "%", "soc");
let chargeTimeRemaining = new InvertedDoughnutValue(document.getElementById("chargingTimeRemaining"), 0, 0, "min", "charging progress");
	
async function getJSON(link){
	return (await fetch(link)).json();
}
	
function timetravelUser(selectedDates, dateStr, instance){
	console.log("select");
	timetravel(selectedDates[0]);
}
	
let timetravelStatus = false;
let timetravelDate;
	
function timetravel(date){
	console.log(date);
	if(date == false || date == undefined || date == null){
		timetravelStatus = false;
		updateCarStatus();
		return;
	}
	timetravelStatus = true;
	timetravelDate = "@"+(date.getTime() / 1000);
	updateCarStatus();
}
	
updateCarStatus();
	
async function updateCarStatus(){
	const carStatus = await getJSON("carStatus.php"+(timetravelStatus ? "?at="+timetravelDate : ""));
	if(carStatus == undefined){
		alert("JSON fail");
		return;
	}
	if(carStatus.error != undefined){
		alert("fail");
		return;
	}
	processCarStatus(carStatus);
}
	
function processCarStatus(carStatus){
	soc.value = carStatus.batterysoc;
	soc.targetSOC = carStatus.targetsoc;
	soc.update();
		
	let now;
	if(timetravel){
		now = Date.parse(carStatus.time);
	}else{
		now = Date.now();
	}
	const elapsedMinutes = Math.round((now - Date.parse(carStatus.lastChargeStartTime)) / 60000);
	//console.log(elapsedMinutes);
	//console.log(carStatus.remainingchargingtime);
	//console.log(carStatus.lastChargeStartTime);
	const realTimeRemaining = now - Date.parse(carStatus.time) + parseInt(carStatus.remainingchargingtime);
	chargeTimeRemaining.max = elapsedMinutes + realTimeRemaining;
	chargeTimeRemaining.value = chargeTimeRemaining.max - realTimeRemaining;
	chargeTimeRemaining.update();
		
	range.setValue(carStatus.remainingrange);
	chargePower.setValue(carStatus.chargepower*10);
	chargeKMPH.setValue(carStatus.chargeratekmph);
	targetTemp.setValue(carStatus.hvactargettemp*10);
		
	let hvacstate;
	switch(carStatus.hvacstate){
	case "heating":
		hvacstate = "heating";
		document.getElementById("hvacstate").classList.add("heat");
		break;
	case "off":
		hvacstate = "hvac off";
		document.getElementById("hvacstate").classList.remove("heat");
		break;
	case "ventilation":
		hvacstate = "ventilating";
		document.getElementById("hvacstate").classList.remove("heat");
		break;
	}
	document.getElementById("hvacstate").innerHTML = hvacstate;
		
	if(carStatus.plugconnectionstate == "connected"){
		document.getElementById("chargingDisplay").style.display = "flex";
		let chargeState;
		switch(carStatus.chargestate){
		case "charging":
			chargeState = "charging...";
			break;
		case "chargePurposeReachedAndConservation":
			chargeState = "holding charge";
			break;
		case "readyForCharging":
			chargeState = "not charging";
			break;
		default:
			chargeState = "unknown: "+carStatus.chargeState;
		}
		document.getElementById("chargingState").innerHTML = chargeState + "<br>" + "Plug " + carStatus.pluglockstate;
	}else{
		document.getElementById("chargingDisplay").style.display = "none";
	}
}