//document.documentElement.setAttribute('data-theme', 'dimmed');
document.documentElement.setAttribute('data-chargeSessions-mobile-label', 'display');
let dateLocaleSetting = "de-DE";

let doughnuts = {};
function createChargeSession(data, last = false){
	const chargeSessions = document.querySelector("#chargeSessions");
	
	let didExist = true;
	let cS = chargeSessions.querySelector("#chargeSession-"+data.id);
	if(cS === null){
		didExist = false;
		cS = document.querySelector("#chargeSession").content.firstElementChild.cloneNode(true);
	}

	cS.id = "chargeSession-"+data.id;
	cS.querySelector("#duration").textContent = Math.floor(data.duration / 3600)+":"+String(Math.round(data.duration % 3600 / 60)).padStart(2, '0');
	const timeOpts = {
		hour: '2-digit',
		minute: '2-digit'
	};
	cS.querySelector("#time").textContent = 
		(new Date(data.startTime)).toLocaleString(dateLocaleSetting, timeOpts)+" - "+
		(new Date(data.endTime ?? Date.now())).toLocaleString(dateLocaleSetting, timeOpts)
	;
	cS.querySelector("#date").textContent = (new Date(data.startTime)).toLocaleString(dateLocaleSetting, {
		day: '2-digit',
		month: '2-digit',
		year: '2-digit'
	});
	cS.querySelector("#avgchargepower").textContent = data.avgChargePower;
	cS.querySelector("#minChargePower").textContent = data.minChargePower
	cS.querySelector("#maxChargePower").textContent = data.maxChargePower;
	cS.querySelector("#kWh").textContent = Math.round(data.chargeEnergy/360, 2)/10;
	cS.querySelector("#range").textContent = (data.rangeEnd - data.rangeStart);
	
	if(!didExist){
		if(!last){
			chargeSessions.querySelector("#chargeSessionHeader").after(cS);
		}else{
			chargeSessions.appendChild(cS);
		}
		doughnuts[data.id] = new SOCchargeDoughnutValue(cS.querySelector("#chargesoc"), data.socStart, data.socEnd, "soc");
	}else{
		doughnuts[data.id].value = data.socStart;
		doughnuts[data.id].end = data.socEnd;
		doughnuts[data.id].update();
	}
}

async function getJSON(link){
	return (await fetch(link)).json();
}

let beginTime = null;
let endTime = null;

async function updateChargingSessions(inital = false){
	let chargingSessions;
	try{
		chargingSessions = await getJSON(
			"../chargingSessions.php"+
			(beginTime == null ? "" : "?beginTime="+Math.round(beginTime.getTime()/1000))+
			(endTime == null ? "" : "&endTime="+Math.round(endTime.getTime()/1000))
		);
	}catch(SyntaxError){
		window.location.replace("../login/login.php?destination=../idView/chargingOverview.html");
		return;
	}
	processChargingSession(chargingSessions, inital);
}

function processChargingSession(chargingSessions, last = false){
	for(const cid in chargingSessions){
		createChargeSession(chargingSessions[cid], last);
	}
}

setInterval(updateChargingSessions, 30000);

updateChargingSessions(true);