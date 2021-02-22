// Variables used by Scriptable.
// These must be at the very top of the file. Do not edit.
// icon-color: deep-green; icon-glyph: car;

//CONFIGURATION

const baseURL = "http://192.168.1.23:8080/vwid3/"
const apiKey = ""

const socThreshold = 95

const forceImageRefresh = false //set to true to refresh the image
const exampleData = false

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

	const carImage = await getImage("car.png", baseURL+"car.png")
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
			break;
		default:
			chargeStatus = "unknown pCS: "+data.plugConnectionState+" cS: "+data.chargeStatus
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
			
	//const chargeInfo = verticalStack(carColumn)
	//chargeInfo.setPadding(0,10,0,10)
	const chargeInfo = carColumn
	
	chargeStatus = chargeInfo.addText(chargeStatus)
	chargeStatus.font = Font.regularSystemFont(10)
	chargeInfo.addSpacer(5)
	if(data.chargeStatus == "slurping"){
		let timeStr = Math.floor(data.chargeMinutesToCompletion / 60) + ":" + String(data.chargeMinutesToCompletion % 60).padStart(2, '0') + "h"
		chargeStateLabel = chargeInfo.addText(data.chargePower + " kW | " + timeStr)
		chargeStateLabel.font = Font.regularSystemFont(10)
	}else{
		chargeInfo.addSpacer(10)
	}
	
	const dataCol1 = verticalStack(wrap)
	
	addFormattedData(dataCol1, "Ladestand", data.batterySOC.toString()+"%")	
	dataCol1.addSpacer(10)
	addFormattedData(dataCol1, "Reichweite", data.remainRange+ "km")
	
	const dataCol2 = verticalStack(wrap)
		
	addFormattedData(dataCol2, "Zielladung", data.targetSOC+"%")	
	dataCol2.addSpacer(10)
	addFormattedData(dataCol2, "Heizung", data.hvacStatus+" ("+data.hvacTargetTemp+"°C)")	
	
	let dF = new DateFormatter()
	dF.useNoDateStyle()
	dF.useShortTimeStyle()
	timedebug = widget.addText("carUpdate: "+data.timestamp+" lastUpdate: "+dF.string(scriptRun))
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
		state["batterySOC"] = "100"
		state["remainRange"] = "999"
		state["targetSOC"] = "100"
		state["hvacStatus"] = "on"
		state["hvacTargetTemp"] = "99"
		state["chargeStatus"] = "slurping"
		state["chargePower"] = "300"
		state["chargeMinutesToCompletion"] = "500"
		state["timestamp"] = "simulated"
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
	url = baseURL+"/datadirect.php?key="+apiKey
	req = new Request(url)
	req.method = "GET"
	apiResult = await req.loadString()
	console.log(apiResult)
	return JSON.parse(apiResult)
}


// get images from local filestore or download them once
// credits: https://gist.github.com/marco79cgn (for example https://gist.github.com/marco79cgn/c3410c8ecc8cb0e9f87409cee7b87338#file-ffp2-masks-availability-js-L234)
//imageName MUST BE the image name in the url!
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
	const req = new Request(imgUrl)
	return await req.loadImage()
}