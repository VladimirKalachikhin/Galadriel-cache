<?php
/*
https://depth.openseamap.org/geoserver/openseamap/wms?SERVICE=WMS&VERSION=1.1.0&REQUEST=GetMap&FORMAT=image%2Fpng&TRANSPARENT=true&LAYERS=openseamap%3Atracks_10m&SRS=EPSG%3A3857&STYLES=&WIDTH=1667&HEIGHT=1172&BBOX=1351133.7634860454%2C6941897.0538716605%2C1812748.4111118838%2C7266439.565579808
https://depth.openseamap.org/geoserver/openseamap/wms?SERVICE=WMS&VERSION=1.1.0&REQUEST=GetMap&FORMAT=image%2Fpng&TRANSPARENT=true&LAYERS=openseamap%3Atracks_100m&SRS=EPSG%3A3857&STYLES=&WIDTH=1667&HEIGHT=1172&BBOX=1351133.7634860454%2C6941897.0538716605%2C1812748.4111118838%2C7266439.565579808
*/
$humanName = array('ru'=>'Народный слой глубин OpenSeaMap','en'=>'OpenSeaMap folk bathymetry');
//$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, один год
$ttl = 86400*30; //cache timeout in seconds время, через которое тайл считается протухшим, один месяц
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$ContentType = 'image/png'; 	// if content type differ then file extension
$minZoom = 2;
$maxZoom = 18;
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
);
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19095,9521,'4b7b92ad');	// to source check; tile number and CRC32b hash

$clientData = array();
$clientData['javascriptOpen'] = "()=>{attributionControl.addAttribution('<img src=\"img/OpenSeaMapDepthScale.png\">')};"; 	// the javascript string, eval'ed before create map. На самом деле, здесь функция не обязательна, если достаточно когда, выпоняющегося во время eval.
$clientData['javascriptClose'] = "()=>{attributionControl.removeAttribution('<img src=\"img/OpenSeaMapDepthScale.png\">')};"; 	// the javascript string, eval'ed after close map. А здесь функция обязательна.

$getURL = function ($z,$x,$y) {
/* 
Меркатор на сфере
https://depth.openseamap.org/geoserver/openseamap/wms?SERVICE=WMS&VERSION=1.1.0&REQUEST=GetMap&FORMAT=image%2Fpng&TRANSPARENT=true&LAYERS=openseamap%3Atracks_10m&SRS=EPSG%3A3857&STYLES=&WIDTH=1667&HEIGHT=1172&BBOX=1351133.7634860454%2C6941897.0538716605%2C1812748.4111118838%2C7266439.565579808
Параметры: 
tracks_100m
tracks_10m
масштабная картинка одинакова для обеих глубин
*/
//$userAgent = randomUserAgent();
//$RequestHead='Referer: https://tileservice.charts.noaa.gov/tileset.html';

$opts = array(
	'http'=>array(
		'method'=>"GET",
		//'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		'request_fulluri'=>TRUE
	)
);
$url = 'https://depth.openseamap.org/geoserver/openseamap/wms?SERVICE=WMS&VERSION=1.1.0&REQUEST=GetMap&FORMAT=image%2Fpng&TRANSPARENT=true';
$url .= '&LAYERS=openseamap%3Atracks_10m&SRS=EPSG%3A3857&STYLES=';
$url .= '&WIDTH=256&HEIGHT=256';
$leftTop = tileNum2ord($z,$x,$y);	// fcommon.php
$rightBottom = tileNum2ord($z,$x+1,$y+1);
$url .= "&BBOX={$leftTop['x']}%2C{$rightBottom['y']}%2C{$rightBottom['x']}%2C{$leftTop['y']}";	//  Bounding box corners (lower left, upper right) https://mapserver.org/es/ogc/wms_server.html?highlight=BBOX
return array($url,$opts);
};
?>
