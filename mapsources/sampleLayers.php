<?php
$humanName = array('ru'=>'Пример слоёв: Погода','en'=>'Sample of Layers: Weather');
$ttl = 60*30; // cache timeout in seconds время, через которое тайл считается протухшим
//$ttl = 10;
$ext = 'png'; 	// tile image type/extension
$minZoom = 1;
$maxZoom = 7;
$freshOnly = TRUE; 	// не показывать протухшие тайлы
$bounds = array('leftTop'=>array('lat'=>61.1856,'lng'=>-23.115),'rightBottom'=>array('lat'=>20.9614,'lng'=>48.0762));
// 
$data = array(
);
$mapname = basename(__FILE__, ".php");
$mapTiles = array(
	"$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r=$mapname&options={\"layer\":0}",
	"$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r=$mapname&options={\"layer\":1}",
	"$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r=$mapname&options={\"layer\":2}",
	"$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r=$mapname&options={\"layer\":3}",
	"$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r=$mapname&options={\"layer\":4}"
);

$getURLoptions=array();	

$getURL = function ($z,$x,$y,$getURLparms=array()) {
if(!isset($getURLparms['layer'])) $getURLparms['layer'] = 0;	// любой запрос без номера слоя будет запросом к умолчальному слою
switch($getURLparms['layer']){
case 1:
	$layer = "/surface_pressure/0h";
	break;
case 2:
	$layer = "/air_temperature/0h";
	break;
case 3:
	$layer = "/precipitation/0h";
	break;
case 4:
	$layer = "/significant_wave_height/0h";
	break;
default:
	$layer = "/wind_stream/0h";
};
$url = 'https://weather.openportguide.de/tiles/actual'.$layer;
$url .= "/".$z."/".$x."/".$y.".png";
$opts = array(
	'http'=>array(
		'header'=>"User-Agent: Galadriel-map"
	)
);
return array($url,$opts);
};
?>
