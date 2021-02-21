class AnimatedValue{
	constructor(displayElement, startValue, unit, decimals = 0, scale = 1, animationLength = 1){
		this.element = displayElement;
		this.value = 0;
		this.setValue(startValue);
		this.unit = unit;
		
		this.decimals = decimals;
		this.scale = scale;
		this.animationLength = animationLength;
	}
	
	animate(timestamp){
		if(this.start == -1){
			this.start = timestamp;
		}
		let elapsed = timestamp - this.start;
		let animationProgress = elapsed / (this.animationLength*1000);
		
		this.element.innerHTML = (Math.round(this.value + ((this.newValue-this.value)*Chart.helpers.easing.effects.easeOutQuart(animationProgress))) / this.scale).toFixed(this.decimals) + this.unit;
		
		if(animationProgress > 1){
			this.value = this.newValue;
			this.element.innerHTML = (this.value / this.scale).toFixed(this.decimals) + this.unit;
			this.start = -1;
		}else{
			window.requestAnimationFrame(this.animate.bind(this));
		}
	}
	
	setValue(value){
		this.start = -1;
		this.newValue = parseFloat(value);
		window.requestAnimationFrame(this.animate.bind(this));
		
	}
}