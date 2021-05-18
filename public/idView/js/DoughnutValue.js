Chart.defaults.global.defaultFontColor = "#fff";
class DoughnutValue{
	constructor(canvas, value, max, unit, legendName){
		this.canvas = canvas;
		this.value = value;
		this.max = max;
		this.unit = unit;
		this.legendName = legendName;
		
		this.animationProgress = 0;

		this.createChart();
		window.addEventListener('resize', function(){
			setTimeout(this.update.bind(this), 100);
		}.bind(this));
	}
	
	createChart(){
		let plugin = {
			afterDraw: function(chart){
				let ctx = chart.ctx;
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.fillStyle = 'white';
				ctx.font = Chart.helpers.fontString(this.canvas.height/(5*this.chart.currentDevicePixelRatio), Chart.defaults.global.defaultFontStyle, Chart.defaults.global.defaultFontFamily)
				let legendOffset = this.chart.options.legend.display ? 15 : 0;
				ctx.fillText(
					Math.round(this.getInnerDisplayValue() * Chart.helpers.easing.effects.easeOutQuart(this.animationProgress))+this.unit,
					this.canvas.width/(2*this.chart.currentDevicePixelRatio), this.canvas.height/(2*this.chart.currentDevicePixelRatio)-legendOffset);
			}.bind(this)
		};
		
		console.log("creating chart...");
		this.chart = new Chart(this.canvas.getContext('2d'), {
			type: 'doughnut',
			data: this.generateData(),
			plugins: [plugin],
			options: {
				aspectRatio: window.innerWidth >= 480 ? 1.6 : 1.1,
				cutoutPercentage: 80,
				tooltips: {
					custom: function(tooltipModel) {
						if(!tooltipModel.body || tooltipModel.body.length < 1){
							tooltipModel.caretSize = 0;
							tooltipModel.xPadding = 0;
							tooltipModel.yPadding = 0;
							tooltipModel.cornerRadius = 0;
							tooltipModel.width = 0;
							tooltipModel.height = 0;
						}
					},
					filter: this.onTooltipFilterCallback,
					callbacks: {
						label: this.onTooltipLabelCallback.bind(this),
					}
				},
				animation: {
					onProgress: function(animation){
						if(this.animationProgress == 1){
							return;
						}
						this.animationProgress = animation.animationObject.currentStep / animation.animationObject.numSteps;
					}.bind(this),
					onComplete: function(animation){
						this.animationProgress = 1;
					}.bind(this)
				},
				legend: {
					position: 'bottom',
					onClick: this.onLegendClick.bind(this)
				},
			}
		});
	}
	
	onTooltipFilterCallback(tooltipItem, data) {
		return tooltipItem.index < 1;
	}
	
	onTooltipLabelCallback(tooltipItem, data){
		let label = data.labels[tooltipItem.index] || '';

		let value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
		if(label){
			label += ': ';
		}
		label += value;
		label += this.unit;

		return label;
	}
	
	onLegendClick(e, legendItem){
		return;
	}
	
	generateData(){
		return {
			datasets: [{
				data: [this.value, Math.max(this.max-this.value, 0)],
				backgroundColor: ['rgba(0,255,0,1)', 'rgba(0,0,0,1)'],
				borderColor: ['rgba(0,255,0,1)', 'rgba(255,0,0,0)']
			}],
			labels: [this.legendName]
		}
	}
	
	getInnerDisplayValue(){
		return this.value;
	}
	
	updateData(){
		this.chart.data.datasets[0].data=[this.value, Math.max(this.max-this.value, 0)];
	}
	
	update(){
		if(
			(window.innerWidth >= 480 && this.chart.options.aspectRatio != 1.6) ||
			(window.innerWidth < 480 && this.chart.options.aspectRatio != 1.1)
		){
			const parentEle = this.canvas.parentElement;
			parentEle.removeChild(this.canvas);
			this.canvas = document.createElement('canvas');
			this.canvas.id = "soc";
			parentEle.appendChild(this.canvas);
			this.createChart();
		}else{
			this.updateData();
		}
		this.chart.options.legend.display = this.canvas.width >= 480;
		this.chart.update();
	}
}
class InvertedDoughnutValue extends DoughnutValue{
	getInnerDisplayValue(){
		return this.max - this.value;
	}
}
class SOCDoughnutValue extends DoughnutValue{
	constructor(canvas, value, max, unit, legendName){
		super(canvas, value, max, unit, legendName)
		this.targetSOC = value
	}
	
	onTooltipFilterCallback(tooltipItem, data) {
		return tooltipItem.index < 2;
	}
	
	onTooltipLabelCallback(tooltipItem, data){
		let label = data.labels[tooltipItem.index] || '';
		
		let value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
		if(label == "targetSOC"){
			value = this.targetSOC;
		}
		if(label){
			label += ': ';
		}
		label += value;
		label += this.unit;

		return label;
	}
	
	onLegendClick(e, legendItem){
		let index = legendItem.index;
		if(index == 0){
			return;
		}
		let n, i, a;
		for(n = 0, i = (this.chart.data.datasets || []).length; n < i; ++n){
			(a = this.chart.getDatasetMeta(n)).data[index] && (a.data[index].hidden = !a.data[index].hidden);
			if(index == 1){
				if(a.data[index].hidden){
					this.chart.data.datasets[0].data[2] = this.max-this.value;
				}else{
					this.chart.data.datasets[0].data[2] = this.max-Math.max(this.value, this.targetSOC);
				}
			}
		}
		this.chart.update();
	}
	
	generateData(){
		return {
			datasets: [{
				data: [this.value, Math.max(this.targetSOC-this.value, 0), this.max-Math.max(this.value, this.targetSOC)],
				backgroundColor: ['rgba(0,255,0,1)', 'rgba(0,0,0,1)', 'rgba(0,0,0,0)'],
				borderColor: ['rgba(0,255,0,1)', 'rgba(255,0,0,1)', 'rgba(0,0,0,0)']
			}],
			labels: [this.legendName, 'targetSOC']
		}
	}
	
	updateData(){
		this.chart.data.datasets[0].data=[this.value, Math.max(this.targetSOC-this.value, 0), this.max-Math.max(this.value, this.targetSOC)];
	}
}
class SOCchargeDoughnutValue extends DoughnutValue{
	constructor(canvas, start, end, legendName){
		super(canvas, start, 100, "%", legendName)
		this.end = end
		this.displayStart = false
		this.chart.options.onHover = function(ev, objects){
			if(objects.length > 0){
				const index = objects[0]._index
				this.displaySlice = index
				this.update()
			}else{
				this.displaySlice = undefined;
			}
		}.bind(this)
		this.update()
	}
	
	onLegendClick(e, legendItem){
		let index = legendItem.index;
		if(index == 0){
			return;
		}
		let n, i, a;
		for(n = 0, i = (this.chart.data.datasets || []).length; n < i; ++n){
			(a = this.chart.getDatasetMeta(n)).data[index] && (a.data[index].hidden = !a.data[index].hidden);
			if(index == 1){
				if(a.data[index].hidden){
					this.chart.data.datasets[0].data[2] = this.max-this.value;
					this.displaySlice = 0;
				}else{
					this.chart.data.datasets[0].data[2] = this.max-this.end;
					this.displaySlice = undefined;
				}
			}
		}
		this.chart.update();
	}
	
	onTooltipFilterCallback(tooltipItem, data) {
		return false;
	}
	
	getInnerDisplayValue(){
		switch(this.displaySlice){
			case 0:
				return this.value;
			case 1:
				return this.end-this.value;
			default:
				return this.end
		}
	}
	
	generateData(){
		return {
			datasets: [{
				data: [this.value, this.end-this.value, this.max-this.end],
				backgroundColor: ['rgba(0,255,0,1)', 'rgba(0,200,200,1)', 'rgba(0,0,0,0)'],
				borderColor: ['rgba(0,255,0,1)', 'rgba(0,200,200,1)', 'rgba(0,0,0,0)']
			}],
			labels: [this.legendName, 'charged']
		}
	}
	
	updateData(){
		this.chart.data.datasets[0].data=[this.value, this.end-this.value, this.max-this.end];
	}
}