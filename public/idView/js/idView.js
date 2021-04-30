function carGraphAspectRatio(){
	const chart = chartStore.carGraph.chart;
	chart.aspectRatio = window.innerWidth >= 400 ? 2 : 1;
	chart.resize();
}
window.addEventListener('resize', carGraphAspectRatio);
carGraphAspectRatio();

const dateLocaleSetting = "de-DE";
flatpickr.localize(flatpickr.l10ns.de);
let timetravelPicker = flatpickr("#timetravel", {dateFormat: "d.m.Y H:i", onChange: timetravelUser, enableTime: true, time_24hr: true});
flatpickr("#graphDateRange", {mode: "range", dateFormat: "d.m.Y", onChange: carGraphRangeUser});

let chargePower = new AnimatedValue(document.getElementById("chargePower"), 0, "kW", 1, 10);
let chargeKMPH = new AnimatedValue(document.getElementById("chargeKMPH"), 0, "km/h");
let range = new AnimatedValue(document.getElementById("range"), 0, "km");
let targetTemp = new AnimatedValue(document.getElementById("hvactargettemp"), 0, "°C", 1, 10);
let soc = new SOCDoughnutValue(document.getElementById("soc"), 0, 100, "%", "soc");
let chargeTimeRemaining = null;

async function getJSON(link){
	return (await fetch(link)).json();
}

function timetravelUser(selectedDates, dateStr, instance){
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
	timetravelDate = "@"+Math.round(date.getTime() / 1000);
	updateCarStatus();
}

updateCarStatus();

setInterval(updateCarStatus, 15000);

async function updateCarStatus(){
	const carStatus = await getJSON("../carStatus.php"+(timetravelStatus ? "?at="+timetravelDate : ""));
	if(carStatus == undefined){
		alert("failed to decode carStatus JSON");
		return;
	}
	if(carStatus.error != undefined){
		alert(carStatus.error);
		return;
	}
	processCarStatus(carStatus);
}

function processCarStatus(carStatus){
	if(carStatus.plugConnectionState == "connected"){
		document.getElementById("chargingDisplay").style.display = "flex";
		let chargeState;
		switch(carStatus.chargeState){
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
		document.getElementById("chargingState").innerHTML = chargeState + "<br>" + "Plug " + carStatus.plugLockState;
		//setTimeout(.bind(chargeTimeRemaining), 50);
	}else{
		document.getElementById("chargingDisplay").style.display = "none";
	}
	soc.value = carStatus.batterySOC;
	soc.targetSOC = carStatus.targetSOC;
	soc.update();

	const carStatusTime = new Date(carStatus.time);
	document.getElementById("carUpdate").textContent = carStatusTime.toLocaleString(dateLocaleSetting, {
		day: '2-digit',
		month: '2-digit'
	}) + " " + carStatusTime.toLocaleString(dateLocaleSetting, {
		hour: '2-digit',
		minute: '2-digit'
	});
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
	const realTimeRemaining = now - Date.parse(carStatus.time) + parseInt(carStatus.remainingChargingTime);
	if(chargeTimeRemaining === null){
		chargeTimeRemaining = new InvertedDoughnutValue(document.getElementById("chargingTimeRemaining"), 0, 0, "min", "charging progress")
	}
	chargeTimeRemaining.max = elapsedMinutes + realTimeRemaining;
	chargeTimeRemaining.value = chargeTimeRemaining.max - realTimeRemaining;
	chargeTimeRemaining.update();

	range.setValue(carStatus.remainingRange);
	chargePower.setValue(carStatus.chargePower*10);
	chargeKMPH.setValue(carStatus.chargeRateKMPH);
	targetTemp.setValue(carStatus.hvacTargetTemp*10);

	let hvacstate;
	document.getElementById("hvacstate").classList.remove("heat");
	document.getElementById("hvacstate").classList.remove("cool");
	switch(carStatus.hvacState){
		case "heating":
			hvacstate = "heating";
			document.getElementById("hvacstate").classList.add("heat");
			break;
		case "cooling":
			hvacstate = "cooling";
			document.getElementById("hvacstate").classList.add("cool");
			break;
		case "off":
			hvacstate = "hvac off";
			break;
		case "ventilation":
			hvacstate = "ventilating";
			break;
		default:
			hvacstate = "unknown: "+carStatus.hvacstate;
	}
	document.getElementById("hvacstate").innerHTML = hvacstate;
}

function carGraphRangeUser(selectedDates, dateStr, instance){
	if(selectedDates[0] != null){
		beginTime = selectedDates[0];
	}else{
		beginTime = new Date();
		beginTime.setDate(beginTime.getDate()-7);
		beginTime.setHours(0,0,0,0);
	}
	if(selectedDates.length > 1){
		endTime = selectedDates[1];
		endTime.setHours(24);
	}else{
		endTime = null;
	}
	updateCarGraph();
}

let currUCStimeout;
chartStore.carGraph.chart.options.onHover = function(ev, objects){
	if(objects.length === 0){
		return;
	}
	chart = objects[0]._chart;
	index = objects[0]._index;
	timetravelPicker.setDate(Date.parse(chart.options.scales.xAxes[0].labels[index]));
	let timestamp = "@"+Math.round(Date.parse(chart.options.scales.xAxes[0].labels[index]) / 1000);
	if(timetravelDate != timestamp){
		timetravelDate = timestamp;
		timetravelStatus = true;
		clearTimeout(currUCStimeout);
		currUCStimeout = setTimeout(updateCarStatus, 100);
	}
};
chartStore.carGraph.chart.update();

let beginTime = new Date();
beginTime.setDate(beginTime.getDate()-7);
beginTime.setHours(0,0,0,0);
let endTime = null;

async function updateCarGraph(){
	console.log(beginTime);
	const graphData = await getJSON(
		"carGraphData.php?beginTime="+Math.round(beginTime.getTime()/1000)+
		(endTime == null ? "" : "&endTime="+Math.round(endTime.getTime()/1000))+
		"&dataBracketing=true"
	);
	if(graphData == undefined){
		alert("failed to decode carGraphData JSON");
		return;
	}
	processCarGraphUpdate(graphData);
}

setInterval(updateCarGraph, 60000);

function processCarGraphUpdate(graphData){
	const chart = chartStore.carGraph.chart;
	chart.options.scales.xAxes[0].labels = graphData.time;
	chart.data.datasets[0].data = graphData.batterySOC;
	chart.data.datasets[1].data = graphData.targetSOC;
	chart.data.datasets[2].data = graphData.remainingRange;
	chart.data.datasets[3].data = graphData.remainingChargingTime;
	chart.data.datasets[4].data = graphData.chargePower;
	chart.data.datasets[5].data = graphData.chargeRateKMPH;
	chart.update();
}