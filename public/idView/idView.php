<?php
require "../login/loginCheck.php";
require "carGraphDataProvider.php";
spl_autoload_register(function ($class){
	if(class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)){
		return false;
	}
	
	$dirNamespace = str_replace("\\", DIRECTORY_SEPARATOR, $class);
	include __DIR__."/ChartJS/".substr($dirNamespace, strpos($dirNamespace, '/')).".php";
	return true;
});
?>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
	<link rel="stylesheet" type="text/css" href="css/datepicker.css">
	<link rel="stylesheet" type="text/css" href="css/idview.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1"/>
	<meta name="apple-mobile-web-app-capable" content="yes"/>
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
	<title>IDView</title>
</head>
<body>
	<div class="container">
		<div class="row">
			<div class="element">
				<img src="car.png" class="responsive" alt="Car">
			</div>
			<div class="element doughnut">
				<canvas id="soc"></canvas>
			</div>
			<div class="element">
				<span class="bigvalue" id="range">000km</span>
				<span class="bigvalue" id="hvacstate">-------</span>
				<span class="bigvalue" id="hvactargettemp">00.0°C</span>
			</div>
		</div>
		<div class="row" id="chargingDisplay" style="display: none">
			<div class="element">
				<span class="bigvalue" id="chargingState">__chargestate__<br>__lockstate__</span>
			</div>
			<div class="element">
				<span class="bigvalue" id="chargePower">000kW</span>
				<span class="bigvalue" id="chargeKMPH">0 km/h</span>
			</div>
			<div class="element doughnut">
				<canvas id="chargingTimeRemaining"></canvas>
			</div>
		</div>
		<div class="row" id="timetravelrow">
			<input type="text" class="flatpickr" id="timetravel" placeholder="timetravel">
			<button id="timetravelclear" onclick="timetravelPicker.clear();">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
				<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
			</svg></button>
		</div>
		<div class="row">
			<?php
			$carGraphData = (new carGraphDataProvider((new DateTime)->sub(new DateInterval("P7D"))->setTime(0, 0)->getTimestamp(), time(), true))->getGraphData();
			
			$graph = (new Graph("carGraph", GraphDisplayType::LINE))->setLineTension(0);

			$xAxis = new Xaxis($carGraphData->time);

			$batterySOC = new Dataset("batterySOC", $carGraphData->batterySOC, new Colour(0, 255, 255));
			$xAxis->addDataset($batterySOC);
			$graph->addYaxis((new Yaxis("e", "%"))->addDataset($batterySOC)->setMinMax(0, 100)->displayGridLines(false)->display(false));
			
			$remainingRange = new Dataset("remainingRange", $carGraphData->remainingRange, new Colour(0, 128, 255));
			$xAxis->addDataset($remainingRange);
			$graph->addYaxis((new Yaxis("r", "km"))->addDataset($remainingRange)->displayGridLines(false));
			
			$remainingChargingTime = new Dataset("remainingChargingTime", $carGraphData->remainingChargingTime, new Colour(128, 0, 255), null, true);
			$xAxis->addDataset($remainingChargingTime);
			$graph->addYaxis((new Yaxis("t", "min"))->addDataset($remainingChargingTime)->displayGridLines(false)->display(false));

			$chargePower = new Dataset("chargePower", $carGraphData->chargePower, new Colour(0, 255, 0));
			$chargePower->setSteppedLine();
			$xAxis->addDataset($chargePower);
			$graph->addYaxis((new Yaxis("p", "kW"))->addDataset($chargePower)->setMinMax(0));

			$chargeRateKMPH = new Dataset("chargeRateKMPH", $carGraphData->chargeRateKMPH, new Colour(0, 255, 0), null, true);
			$chargeRateKMPH->setSteppedLine();
			$xAxis->addDataset($chargeRateKMPH);
			$graph->addYaxis((new Yaxis("k", "km/h"))->addDataset($chargeRateKMPH)->setMinMax(0)->display(false));

			$graph->setXaxis($xAxis);

			$graph->canvas();
			?>
		</div>
		<div class="row">
			<input type="text" class="flatpickr" id="graphDateRange">
		</div>
	</div>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/luxon@1.26.0/build/global/luxon.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@0.2.1"></script>
	<script src="js/DoughnutValue.js"></script>
	<script src="js/AnimatedValue.js"></script>
	<script src="https://npmcdn.com/flatpickr/dist/l10n/de.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<?php
	$graph->render();
	?>
	<script src="js/idView.js"></script>
</body>