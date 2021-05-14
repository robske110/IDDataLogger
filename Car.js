// Variables used by Scriptable.
// These must be at the very top of the file. Do not edit.
// icon-color: deep-green; icon-glyph: car;
// CONFIGURATION

const baseURL = ""
const apiKey = ""

const rangeInMiles = false //set to true to show range in miles
const showFinishTime = true //set to false to hide charge finish time
const forceImageRefresh = false //set to true to refresh the image

const exampleData = false

const timetravel = null; //set to a unix timestamp to emulate the script being run at that time (seconds!)

const socThreshold = 95 //not implemented

// WIDGET VERSION: v0.0.6-InDev

// Created by robske_110 24.01.2020
// This script is originally inspired from https://gist.github.com/mountbatt/772e4512089802a2aa2622058dd1ded7

let scriptRun = new Date()
if(timetravel !== null){
	scriptRun = new Date(timetravel*1000);
}


// Translations
const translations = {
	en: {
		chargeStatus: {
			disconnected: "Disconnected",
			holdingCharge: "holding charge",
			connected: "connected",
			charging: "charging…"
		},
		soc: "SOC",
		range: "Range",
		targetSOC: "Target SOC",
		hvac: "HVAC",
		hvacStatus: {
			heating: "heating",
			cooling: "cooling",
			ventilation: "ventilating",
			off: "off"
		}
	},
	de: {
		chargeStatus: {
			disconnected: "Entkoppelt",
			holdingCharge: "Ladezustand halten",
			connected: "Verbunden",
			charging: "Lädt…"
		},
		soc: "Ladezustand",
		range: "Reichweite",
		targetSOC: "Zielladung",
		hvac: "Klimaanlage",
		hvacStatus: {
			heating: "Heizen",
			cooling: "Kühlen",
			ventilation: "Lüften",
			off: "Aus"
		}
	}
}

function getTranslatedText(key){
	let lang = Device.language();
	let translation = translations[lang];
	if(translation == undefined){
		translation = translations.en;
	}
	let nested = key.split(".");
	key.split(".").forEach(function(element){
		translation = translation[element];
	});
	return translation;
}

let widget = await createWidget()

// present the widget in app
if (!config.runsInWidget) {
	await widget.presentMedium()
}
Script.setWidget(widget)
Script.complete()

// adds a vertical stack to widgetStack
function verticalStack(widgetStack){
	let stack = widgetStack.addStack()
	stack.layoutVertically()
	return stack
}

// adds a value - title pair
function addFormattedData(widgetStack, dataTitle, dataValue){
	let stack = widgetStack.addStack()
	stack.layoutVertically()
	const label = stack.addText(dataTitle)
	label.font = Font.mediumSystemFont(12)
	const value = stack.addText(dataValue)
	value.font = Font.boldSystemFont(16)
}

// build the widget
async function createWidget() {
	let widget = new ListWidget()
	const data = await getData()

	widget.setPadding(20, 15, 20, 15) //top, leading, bottom, trailing
	widget.backgroundColor = Color.dynamic(new Color("eee"), new Color("111"))

	const wrap = widget.addStack()
	//wrap.centerAlignContent()
	wrap.spacing = 15

	const carColumn = verticalStack(wrap)

	carColumn.addSpacer(5)

	const carImage = await getImage(
		baseURL.substr(baseURL.indexOf("://")+3).replace("/", "_")+"-car.png",
		baseURL+"/carPicture.php?key="+apiKey)
	carColumn.addImage(carImage)

	//carColumn.addSpacer(5)

	let chargeStatus

	switch (data.plugConnectionState){
		case "disconnected":
			chargeStatus = "⚫ "+getTranslatedText("chargeStatus.disconnected")
			break;
		case "connected":
			//widget.refreshAfterDate = new Date(Date.now() + 300) //increase refresh rate?
			switch (data.chargeState){
				case "readyForCharging":
					chargeStatus = "🟠 "+getTranslatedText("chargeStatus.connected")
					break;
				case "chargePurposeReachedAndConservation":
					chargeStatus = "🟢 "+getTranslatedText("chargeStatus.holdingCharge")
					break;
				case "charging":
					chargeStatus = "⚡ "+getTranslatedText("chargeStatus.charging")
					break;
				default:
					chargeStatus = "unknown cS: "+data.chargeState
			}
			let plugLockStatus;
			switch (data.plugLockState){
				case "locked":
					plugLockStatus = " (🔒)"
					break;
				case "unlocked":
					plugLockStatus = " (🔓)"
					break;
				case "invalid":
					plugLockStatus = " (❌)"
					break;
				default:
					plugLockStatus = "unknown pLS: "+data.plugConnectionState
					break;
			}
			chargeStatus = chargeStatus + plugLockStatus;
			break;
		default:
			chargeStatus = "unknown pCS: "+data.plugConnectionState+" cS: "+data.chargeState
	}

	//const chargeInfo = verticalStack(carColumn)
	//chargeInfo.setPadding(0,10,0,10)
	const chargeInfo = carColumn

	let dF = new DateFormatter()
	dF.useNoDateStyle()
	dF.useShortTimeStyle()

	chargeStatus = chargeInfo.addText(chargeStatus)
	chargeStatus.font = Font.regularSystemFont(10)
	chargeInfo.addSpacer(5)
	let dataTimestamp = null;
	if(!Number.isNaN(Date.parse(data.time))){
		dataTimestamp = new Date(Date.parse(data.time));
	}
	if(data.chargeState === "charging" || data.chargeState === "chargePurposeReachedAndConservation"){
		let realRemainChgTime = data.remainingChargingTime;
		let finishTime = ""
		if(dataTimestamp != null){
			realRemainChgTime -= (scriptRun.getTime() - dataTimestamp.getTime()) / 60000;
			realRemainChgTime = Math.max(0, realRemainChgTime);
			finishTime = " ("+dF.string(new Date(dataTimestamp.getTime() + realRemainChgTime * 60000))+")";
		}
		let timeStr = Math.floor(realRemainChgTime / 60) + ":" + String(Math.round(realRemainChgTime % 60)).padStart(2, '0') + "h"
		let chargeStateLabel = chargeInfo.addText(data.chargePower + " kW | " + timeStr + (showFinishTime ? finishTime : ""))
		chargeStateLabel.font = Font.regularSystemFont(10)
	}else{
		chargeInfo.addSpacer(10)
	}

	const dataCol1 = verticalStack(wrap)

	addFormattedData(dataCol1, getTranslatedText("soc"), data.batterySOC.toString()+"%")
	dataCol1.addSpacer(10)
	let range
	if(!rangeInMiles){
		range = data.remainingRange+"km";
	}else{
		range = Math.round(data.remainingRange/1.609344)+"mi";
	}
	addFormattedData(dataCol1, getTranslatedText("range"), range)

	const dataCol2 = verticalStack(wrap)

	addFormattedData(dataCol2, getTranslatedText("targetSOC"), data.targetSOC+"%")
	dataCol2.addSpacer(10)
	let hvacStatus;
	switch (data.hvacState){
		case "heating":
			hvacStatus = getTranslatedText("hvacStatus.heating");
			break;
		case "cooling:":
			hvacStatus = getTranslatedText("hvacStatus.cooling");
			break;
		case "ventilation":
			hvacStatus = getTranslatedText("hvacStatus.ventilation");
			break;
		case "off":
			hvacStatus = getTranslatedText("hvacStatus.off");
			break;
		default:
			hvacStatus = "unknown hS: "+date.hvacState;
	}
	addFormattedData(dataCol2, getTranslatedText("hvac"), hvacStatus+" ("+data.hvacTargetTemp+"°C)")

	timedebug = widget.addText("carUpdate "+(dataTimestamp == null ? data.time : dF.string(dataTimestamp))+" (widget "+dF.string(scriptRun)+")")
	timedebug.font = Font.lightSystemFont(8)
	timedebug.textColor = Color.dynamic(Color.lightGray(), Color.darkGray())
	timedebug.rightAlignText()
	return widget;
}


// fetch data
async function getData() {
	let state
	if(exampleData || baseURL == ""){
		state = {};
		state["batterySOC"] = "40"
		state["remainingRange"] = "150"
		state["remainingChargingTime"] = "61"
		state["chargeState"] = "charging"
		state["chargePower"] = "100"
		state["targetSOC"] = "100"
		state["plugConnectionState"] = "connected"
		state["plugLockState"] = "locked"
		state["hvacState"] = "heating"
		state["hvacTargetTemp"] = "21.5"

		state["time"] = "simulated"
	}else{
		state = getJSON()
	}

	/*let currentDate = ;
    let newDate = new Date((new Date).getTime()+1000);
	chargeReached = new Notification()
	chargeReached.identifier = "SoCReached"
	chargeReached.title = "ID.3 🔋 Geladen"
	chargeReached.body = "Die Batterie ist zu " + socThreshold + "% geladen!"
	chargeReached.sound = "complete"
	chargeReached.setTriggerDate(newDate)
	chargeReached.schedule()*/

	return state
}

async function getJSON(){
	url = baseURL+"/carStatus.php?key="+apiKey
	if(timetravel !== null){
		url += "&at=@"+timetravel
	}
	req = new Request(url)
	req.method = "GET"
	apiResult = await req.loadString()
	console.log(apiResult)
	return JSON.parse(apiResult)
}


// get images from local filestore or download them once
// credits: https://gist.github.com/marco79cgn (for example https://gist.github.com/marco79cgn/c3410c8ecc8cb0e9f87409cee7b87338#file-ffp2-masks-availability-js-L234)
async function getImage(imageName, imgUrl){
	let fm = FileManager.local()
	let dir = fm.documentsDirectory()
	let path = fm.joinPath(dir, imageName)
	if(fm.fileExists(path) && !forceImageRefresh){
		return fm.readImage(path)
	}else{
		// download once
		let iconImage = await loadImage(imgUrl)
		fm.writeImage(path, iconImage)
		return iconImage
	}
}

async function loadImage(imgUrl){
	console.log("fetching_pic");
	const req = new Request(imgUrl)
	return await req.loadImage()
}