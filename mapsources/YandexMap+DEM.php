<?php
/**/
$humanName = array('ru'=>'Карта Яндекс + рельеф','en'=>'YandexMap + DEM');
$minZoom = 0;
$maxZoom = 24;

$mapTiles = array(
	array(
		"mapName"=>"YandexMap.EPSG3395",
		"mapParm"=>array(
		)
	),
	array(
		"mapName"=>"mapterhorn.DEM",
		"mapParm"=>array(
		)
	)
);
$getTile = null;	// no way to get tile for this map
?>
