<?php
//$ttl = 86400; // 1 day cache timeout in seconds время, через которое тайл считается протухшим
$ttl = 60; // 1 day cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 1;
$maxZoom = 7;
$freshOnly = TRUE; 	// не показывать протухшие тайлы
// 
$data = array(
	'default'=>array('wind_stream','0h'),
	'layers'=>array('wind_stream','surface_pressure','air_temperature','precipitation','significant_wave_height'),
	'forecast'=>array('0h', '6h', '12h', '24h', '36h', '48h', '60h', '72h')
);

$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y,$layer) {
$url = "https://weather.openportguide.de/tiles/actual/$layer";
$url .= "/".$z."/".$x."/".$y.".png";
return $url;
}
EOFU;

$javascript = <<<'EOJO'
additionalTileCachePath = "/wind_stream/0h";
//alert(additionalTileCachePath);
if(typeof weatherTab == 'undefined') {
	// create Weather tab
	let newLi = document.createElement("li");
	newLi.setAttribute("id", "weatherTab");
	newLi.innerHTML = '<a href="#weather" role="tab"><img src="img/weather.svg" alt="Weather forecast" width="70%"></a>'
	featuresList.appendChild(newLi);
	// Create Weather tab pane
	let newTabPane = document.createElement('div');
	newTabPane.setAttribute('id','weather');
	newTabPane.className = 'leaflet-sidebar-pane';
	newTabPane.innerHTML = `
		<!-- Погода -->
		<h1 class="leaflet-sidebar-header leaflet-sidebar-close" onClick='window["Weather"].remove();'> Погода <span class="leaflet-sidebar-close-icn"><img src="img/Triangle-left.svg" alt="close" width="16px"></span></h1>
	`;
	tabPanes.append(newTabPane);
	alert();
}
else {
	if(weatherTab.className == 'disabled') weatherTab.className = '';
}
/*
		<!-- Погода -->
		<div class="leaflet-sidebar-pane" id="weather">
			<h1 class="leaflet-sidebar-header leaflet-sidebar-close" onClick='window["Weather"].remove();'> Погода <span class="leaflet-sidebar-close-icn"><img src="img/Triangle-left.svg" alt="close" width="16px"></span></h1>
			<form id="weatherLayers" onSubmit="
				// получим из формы, что показывать
				let weatherLayers = new Array();
				let i;
				for(i=0; i<weatherLayer.length; i++) {
					if(weatherLayer[i].checked) weatherLayers.push(weatherLayer[i].value);
				}
				for(i=0; i<weatherForecast.length; i++) {
					if(weatherForecast[i].checked) break;
				}
				//alert(weatherForecast[i].value+'<br>'+weatherLayers);
				// Добавим слои в уже существующую LayerGroup
				window['Weather'].clearLayers(); 	// удалить все слои
				for(let ii=0; ii<weatherLayers.length; ii++) {
					let mapnameThis = 'Weather/'+weatherLayers[ii]+'/'+weatherForecast[i].value
					let tileCacheURIthis = tileCacheURI.replace('{map}',mapnameThis); 	// глобальная переменная
					//alert(tileCacheURIthis);
					window['Weather'].addLayer(L.tileLayer(tileCacheURIthis, {})); 	// добавить слой
				}
				return false;
			">
				<table  style="font-size:120%;margin:0 1rem 1rem 0;float:right;">
					<caption><h3>Карта</h3></caption>
					<tr style="height:3rem;"><td style="text-align:right;">Ветер</td><td width=20% style="text-align:center;"><input type="checkbox" name="weatherLayer" value="wind_stream"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">Давление</td><td width=20% style="text-align:center;"><input type="checkbox" name="weatherLayer" value="surface_pressure"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">Температура</td><td width=20% style="text-align:center;"><input type="checkbox" name="weatherLayer" value="air_temperature"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">Осадки</td><td width=20% style="text-align:center;"><input type="checkbox" name="weatherLayer" value="precipitation"></td></tr>
					<tr style="height:3rem;"><td style="text-align:right;">Волнение моря</td><td width=20% style="text-align:center;"><input type="checkbox" name="weatherLayer" value="significant_wave_height"></td></tr>
				</table>
				<table style="font-size:120%;margin:0 0 1rem 0;">
					<caption><h3>Прогноз, час.</h3></caption>
					<tr style="height:3rem;"><td>O</td><td><input type="radio" name="weatherForecast" value="0h"></td></tr>
					<tr style="height:3rem;"><td>6</td><td><input type="radio" name="weatherForecast" value="6h"></td></tr>
					<tr style="height:3rem;"><td>12</td><td><input type="radio" name="weatherForecast" value="12h"></td></tr>
					<tr style="height:3rem;"><td>24</td><td><input type="radio" name="weatherForecast" value="24h"></td></tr>
					<tr style="height:3rem;"><td>36</td><td><input type="radio" name="weatherForecast" value="36h"></td></tr>
					<tr style="height:3rem;"><td>48</td><td><input type="radio" name="weatherForecast" value="48h"></td></tr>
					<tr style="height:3rem;"><td>60</td><td><input type="radio" name="weatherForecast" value="60h"></td></tr>
					<tr style="height:3rem;"><td>72</td><td><input type="radio" name="weatherForecast" value="72h"></td></tr>
				</table>	
				<input type="submit">			
			</form>
		</div>
*/
EOJO;
$data['javascriptOpen'] = $javascript;

$javascript = <<<'EOJC'
weather.remove();
weatherTab.remove();
EOJC;
$data['javascriptClose'] = $javascript;
?>
