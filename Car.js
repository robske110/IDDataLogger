// Variables used by Scriptable.
// These must be at the very top of the file. Do not edit.
// icon-color: deep-green; icon-glyph: car;
//CONFIGURATION

const baseURL = ""
const apiKey = ""

const forceImageRefresh = false //set to true to refresh the image
const exampleData = true

const socThreshold = 95 //not implemented

// Created by robske_110 24.01.2020
// This script is orginally inspired from https://gist.github.com/mountbatt/772e4512089802a2aa2622058dd1ded7

const scriptRun = new Date()

let widget = await createWidget()

// used for debugging if script runs inside the app
if (!config.runsInWidget) {
	await widget.presentMedium()
}
Script.setWidget(widget)
Script.complete()


function verticalStack(widgetStack){
	let stack = widgetStack.addStack()
	stack.layoutVertically()
	return stack
}

function addFormattedData(widgetStack, dataTitle, dataValue){
	let stack = widgetStack.addStack()
	stack.layoutVertically()
	const label = stack.addText(dataTitle)
	label.font = Font.mediumSystemFont(12)
	const value = stack.addText(dataValue)
	value.font = Font.boldSystemFont(16)
}

// build the widget
async function createWidget(items) {
	let widget = new ListWidget()
	const data = await getData()

	widget.setPadding(20, 15, 20, 15) //top, leading, bottom, trailing
	widget.backgroundColor = Color.dynamic(new Color("eee"), new Color("111"))

	const wrap = widget.addStack()
	//wrap.centerAlignContent()
	wrap.spacing = 15

	const carColumn = verticalStack(wrap)

	carColumn.addSpacer(5)

	const carImage = await getImage("car.png", baseURL+"/carPicture.php?key="+apiKey)
	let carImageElement = carColumn.addImage(carImage)

	//carColumn.addSpacer(5)

	let chargeStatus

	switch (data.plugConnectionState){
		case "disconnected":
			chargeStatus = "⚫ Entkoppelt"
			break;
		case "connected":
			//widget.refreshAfterDate = new Date(Date.now() + 300) //increase refresh rate?
			switch (data.chargeStatus){
				case "readyForCharging":
					chargeStatus = "🟠 Ladezustand halten"
					break;
				case "chargePurposeReachedAndConservation":
					chargeStatus = "🟢 Verbunden"
					break;
				case "charging":
					chargeStatus = "⚡ Lädt…"
					break;
				default:
					chargeStatus = "unknown cS: "+data.chargeStatus
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
			chargeStatus = "unknown pCS: "+data.plugConnectionState+" cS: "+data.chargeStatus
	}

	//const chargeInfo = verticalStack(carColumn)
	//chargeInfo.setPadding(0,10,0,10)
	const chargeInfo = carColumn

	chargeStatus = chargeInfo.addText(chargeStatus)
	chargeStatus.font = Font.regularSystemFont(10)
	chargeInfo.addSpacer(5)
	let dataTimestamp = null;
	if(!Number.isNaN(Date.parse(data.time))){
		dataTimestamp = new Date(Date.parse(data.time));
	}
	if(data.chargeStatus == "charging" || data.chargeStatus == "chargePurposeReachedAndConservation"){
		let realRemainChgTime = data.remainingChargingTime;
		if(dataTimestamp != null){
			realRemainChgTime -= (Date.now() - dataTimestamp.getTime()) / 60000;
		}
		let timeStr = Math.floor(realRemainChgTime / 60) + ":" + String(realRemainChgTime % 60).padStart(2, '0') + "h"
		chargeStateLabel = chargeInfo.addText(data.chargePower + " kW | " + timeStr)
		chargeStateLabel.font = Font.regularSystemFont(10)
	}else{
		chargeInfo.addSpacer(10)
	}

	const dataCol1 = verticalStack(wrap)

	addFormattedData(dataCol1, "Ladestand", data.batterySOC.toString()+"%")
	dataCol1.addSpacer(10)
	addFormattedData(dataCol1, "Reichweite", data.remainingRange+ "km")

	const dataCol2 = verticalStack(wrap)

	addFormattedData(dataCol2, "Zielladung", data.targetSOC+"%")
	dataCol2.addSpacer(10)
	addFormattedData(dataCol2, "Heizung", data.hvacState+" ("+data.hvacTargetTemp+"°C)")

	let dF = new DateFormatter()
	dF.useNoDateStyle()
	dF.useShortTimeStyle()
	timedebug = widget.addText("carUpdate: "+(dataTimestamp == null ? data.time : dF.string(dataTimestamp))+" lastUpdate: "+dF.string(scriptRun))
	timedebug.font = Font.lightSystemFont(8)
	timedebug.textColor = Color.dynamic(Color.lightGray(), Color.darkGray())
	timedebug.rightAlignText()
	return widget;
}



// fetch all data
async function getData() {
	let state
	if(exampleData){
		state = {};
		state["batterySOC"] = "40"
		state["remainingRange"] = "150"
		state["remainingChargingTime"] = "61"
		state["targetSOC"] = "100"
		state["hvacState"] = "heating"
		state["hvacTargetTemp"] = "21.5"
		state["chargeStatus"] = "charging"
		state["plugConnectionState"] = "connected"
		state["plugLockState"] = "locked"
		state["chargePower"] = "100"

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