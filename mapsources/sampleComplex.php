<?php
/**/
$humanName = array('ru'=>'Пример составной карты: OpenTopo и OpenNautical поверх','en'=>'Complex map sample: OpenTopo and OpenNautical above');
$minZoom = 0;
$maxZoom = 24;

$mapTiles = array(
	array(
		"mapName"=>"OpenTopoMap",
		"mapParm"=>array(
			//"mapTiles"=>"$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r={map}&options={\"prepareTileImg\":true}"
			//"requestOptions"=>array("prepareTileImg"=>true)
		)
	),
	array(
		"mapName"=>"OpenNauticalChart",
		"mapParm"=>array(
		)
	)
);
$getTile = null;	// no way to get tile for this map
?>
