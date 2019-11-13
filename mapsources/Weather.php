<?php
//$ttl = 86400; // 1 day cache timeout in seconds время, через которое тайл считается протухшим
$ttl = 60; // 1 day cache timeout in seconds время, через которое тайл считается протухшим
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
function getURL($z,$x,$y,$layer) {
if(!$layer) $layer="/wind_stream/0h";
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
		tab: '<img src="img/logo_tk_weatherservice.png" alt="Weather forecast" width="70%">',  // content can be passed as HTML string,
		pane: `
			<div style="height:100%;">
			by <a href="http://weather.openportguide.de/index.php/en/" target="_blank">Thomas Krüger Weather Service</a><br>
			<form id="weatherLayers" style="position:relative; z-index:10; width:95%;">
				<table  style="font-size:120%;float:right;word-break: break-all;width:70%;">
					<caption><h3>${mapTXT}</h3></caption>
					<tr style="height:3rem;"><td style="text-align:right;">${windTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="wind_stream" checked ></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${pressureTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="surface_pressure"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${temperatureTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="air_temperature"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${precipitationTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="precipitation"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">${waveTXT}</td><td style="text-align:center;"><input type="checkbox" name="weatherLayer" value="significant_wave_height"></td></tr>
				</table>
				<table style="font-size:120%;width:25%;">
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
					<tr style="height:3rem;"><td>48</td><td><input type="radio" name="weatherForecast" value="48h" onClick="alert('
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
