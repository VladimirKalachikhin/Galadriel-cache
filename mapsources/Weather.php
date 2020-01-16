<?php
//$ttl = 86400; // 1 day cache timeout in seconds время, через которое тайл считается протухшим
$ttl = 60*60*6; // 1 day cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 1;
$maxZoom = 7;
$freshOnly = TRUE; 	// не показывать протухшие тайлы
$opts = array(
	'http'=>array(
		'header'=>"User-Agent: Galadriel-map\r\n"
	)
);
// 
$data = array(
);

$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y,$layer="/wind_stream/0h") {
$url = "https://weather.openportguide.de/tiles/actual/$layer";
$url .= "/".$z."/".$x."/".$y.".png";
return $url;
}
EOFU;
// При открытии
$javascript = <<<'EOJO'
let title, mapTXT, forecastTXT, windTXT, pressureTXT, temperatureTXT, precipitationTXT, waveTXT, windLegendTXT;
if(window.navigator.language.indexOf('ru')==-1) { 	// клиент нерусский
	title = 'Weather';
	mapTXT = 'Layer';
	forecastTXT = 'Fore<wbr>cast, hours';
	windTXT = 'Wind, m/sec';
	pressureTXT = 'Pressure, hPa'; 
	temperatureTXT = 'Temperature, °C';
	precipitationTXT = 'Precipitation, mm';
	waveTXT = 'Swell, m';
	windLegendTXT = 'Wind legend, m/sec';
}
else {
	title = 'Погода';
	mapTXT = 'Карта';
	forecastTXT = 'Прог<wbr>ноз, час.';
	windTXT = 'Ветер,м/сек';
	pressureTXT = 'Давление,гПа'; 
	temperatureTXT = 'Температура,°C';
	precipitationTXT = 'Осадки,мм';
	waveTXT = 'Волнение моря,м';
	windLegendTXT = 'Шкала ветра, м/сек';
}
if(typeof weatherTab == 'undefined') {
	additionalTileCachePath = ["/wind_stream/0h"]; 	// default map
	// create Weather tab
	let panelContent = {
		id: 'weatherTab',                     // UID, used to access the panel
		title: title,              // an optional pane header
		tab: '<img src="data:image/png;base64,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" alt="Weather forecast" width="70%">',  // content can be passed as HTML string,
		pane: `
			<div style="height:100%;">
			by <a href="http://weather.openportguide.de/index.php/en/" target="_blank">Thomas Krüger Weather Service</a><br>
			<form id="weatherLayers" style="position:relative; z-index:10; width:95%;">
				<table  style="font-size:120%;float:right;word-break: break-all;width:75%;">
					<caption><h3>${mapTXT}</h3></caption>
					<tr style="height:3rem;"><td style="text-align:right;">${windTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="wind_stream" checked ></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${pressureTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="surface_pressure"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${temperatureTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="air_temperature"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${precipitationTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="precipitation"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${waveTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="significant_wave_height"></td></tr>
				</table>
				<table style="font-size:120%;width:20%;">
					<caption><h3>${forecastTXT}</h3></caption>
					<tr style="height:3rem;"><td>O</td><td><input type="radio" name="weatherForecast" value="0h" checked onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>6</td><td><input type="radio" name="weatherForecast" value="6h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>12</td><td><input type="radio" name="weatherForecast" value="12h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>24</td><td><input type="radio" name="weatherForecast" value="24h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>36</td><td><input type="radio" name="weatherForecast" value="36h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>48</td><td><input type="radio" name="weatherForecast" value="48h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>60</td><td><input type="radio" name="weatherForecast" value="60h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
					<tr style="height:3rem;"><td>72</td><td><input type="radio" name="weatherForecast" value="72h" onClick="
																									additionalTileCachePath = [];
																									for(let layer of document.querySelectorAll('input[name=weatherLayer]:checked')) {
																										additionalTileCachePath.push('/'+layer.value+'/'+this.value);
																									};
																									if(! additionalTileCachePath) {additionalTileCachePath = ['/wind_stream/0h']};
																									displayMap('Weather');
																									"></td></tr>
				</table>	
			</form>
			<div style="text-align: right; position: absolute; bottom: 0; right: 0; z-index:1;">
			<span style="background:rgb(160, 0, 200)">0-2 </span><br>
			<span style="background:rgb(130, 0, 220)">2-3 </span><br>
			<span style="background:rgb(30, 60, 255)">3-5 </span><br>
			<span style="background:rgb(0, 160, 255)">5-7 </span><br>
			<span style="background:rgb(0, 200, 200)">7-10 </span><br>
			<span style="background:rgb(0, 210, 140)">10-12 </span><br>
			<span style="background:rgb(0, 220, 0)">12-15 </span><br>
			<span style="background:rgb(160, 230, 50)">15-18 </span><br>
			<span style="background:rgb(230, 220, 50)">18-21 </span><br>
			<span style="background:rgb(230, 175, 45)">21-25 </span><br>
			<span style="background:rgb(240, 130, 40)">25-29 </span><br>
			<span style="background:rgb(250, 60, 60)">29-35 </span><br>
			<span style="background:rgb(240, 0, 130)">35&gt; </span><br>
			${windLegendTXT}
			</div>
			<div>
		`,        // DOM elements can be passed, too
	};
	sidebar.addPanel(panelContent);
}
else {
	sidebar.enablePanel('weatherTab');
}

EOJO;
$data['javascriptOpen'] = $javascript;
// При закрытии
$javascript = <<<'EOJC'
sidebar.removePanel('weatherTab');
EOJC;
$data['javascriptClose'] = $javascript;
?>
