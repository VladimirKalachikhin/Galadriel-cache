<?php
$humanName = array('ru'=>'Карта Яндекс народная','en'=>'Yandex folk map');
$ttl = 60*60*24*30*6*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
// $ttl = 0; 	// тайлы не протухают никогда
$noTileReTry = 60*60; 	// no tile timeout, sec. Время, через которое переспрашивать тайлы, которые не удалось скачать. OpenTopoMap банит скачивальщиков, поэтому короткое.
$ext = 'png'; 	// tile image type/extension
$ContentType = 'image/png'; 	// if content type differ then file extension
//$EPSG=3395;
$EPSG="EPSG:3395";
$minZoom = 6;
$maxZoom = 19;

// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'ebc7b955');	// to source check; tile number and CRC32b hash

$getURLoptions['r'] = pathinfo(__FILE__, PATHINFO_FILENAME);	// $getURLoptions будет передан в $getURL

$getURL = function ($z,$x,$y,$getURLoptions=array()) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
Карта в EPSG=3395 !!!! Меркатор на эллипсоиде
*/
//$userAgent = randomUserAgent();
//$baseHeaders = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8\r\nAccept-Encoding: gzip, deflate, br, zstd\r\nAccept-Language: en-US,en;q=0.5\r\nConnection: keep-alive\r\n";
//$referer = "Referer: https://yandex.com/\r\n";
$RequestHead=$baseHeaders.$referer;

$opts = array(
	'http'=>array(
		'method'=>"GET",
		//'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead",
		//'proxy'=>'tcp://127.0.0.1:8118',
		'timeout' => 20,
		//'request_fulluri'=>TRUE,	// Должно быть false!!!!
	),
//	'socket' => array(
//		'bindto' => '0:0', // Force IPv4
//	)
);

$DefURLBase = 'https://0'.rand(1,4).'.core-nmaps-renderer-nmaps.maps.yandex.net/tile2?';
$DefURLBase .= "z=$z&x=$x&y=$y";
$DefURLBase .= '&l=mpmap&sl=ad_map_area%2Cad_border_all_map%2Cad_cnt%2Caddr_regular%2Czipcode_z12%2Cpoi_entrance%2Chydro_fc%2Chydro_line_map%2Chydro_area%2Chydro_point%2Crd_oneway%2Crd_junctions%2Crd_all_map%2Curban_roadnet%2Cfence_map%2Cbld_regular%2Cpoi_auto%2Cpoi_service%2Cpoi_government%2Cpoi_urban%2Cpoi_food%2Cpoi_other%2Cpoi_culture%2Cpoi_medicine%2Cpoi_edu%2Cpoi_leisure%2Cpoi_industry%2Cpoi_backa_null%2Cpoi_religion%2Cpoi_sport%2Cpoi_construction%2Cpoi_shopping%2Cpoi_finance%2Cparking_lot_linear%2Cparking_lot_regular%2Cvegetation_plant_color%2Cvegetation_area%2Cvegetation_cnt%2Crelief_line_map%2Crelief_area%2Crelief_point%2Curban_areal%2Ctransport_terminal%2Ctransport_airport%2Ctransport_helicopter%2Ctransport_metro_exit%2Ctransport_waterway_line%2Ctransport_railway%2Ctransport_metro_line%2Ctransport_stop%2Ctransport_railway_platform%2Ctransport_waterway_stop%2Ctransport_railway_station%2Ctransport_metro_station%2Ctransport_airport_terminal%2Ctransport_tram_line%2Croad_surface_map&token=1534368522:170042125:core.1534368522:169099502:social';
changeTORnode($getURLoptions['r']);
return array($DefURLBase,$opts);
}; // end function getURL
?>
