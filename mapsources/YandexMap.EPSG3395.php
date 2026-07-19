<?php
$humanName = array('ru'=>'Карта Яндекс','en'=>'Yandex map');
$mapDescription = array('ru'=>'Обновляется более-менее оперативно, но военных объектов нет, даже забора.
Даже тех, что есть на Единой картографической основе.
','en'=>'
');
$ttl = 60*60*24*30*6*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
// $ttl = 0; 	// тайлы не протухают никогда
$noTileReTry = 60*60; 	// no tile timeout, sec. Время, через которое переспрашивать тайлы, которые не удалось скачать. OpenTopoMap банит скачивальщиков, поэтому короткое.
$ext = 'png'; 	// tile image type/extension
$ContentType = 'image/png'; 	// if content type differ then file extension
//$EPSG=3395;
$EPSG="EPSG:3395";
$minZoom = 0;
$maxZoom = 19;

// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'c1f5c576');	// to source check; tile number and CRC32b hash

$getURLoptions['r'] = pathinfo(__FILE__, PATHINFO_FILENAME);	// $getURLoptions будет передан в $getURL

$getURL = function ($z,$x,$y,$getURLoptions=array()) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
Карта в EPSG=3395 !!!! Меркатор на эллипсоиде
https://core-renderer-tiles.maps.yandex.net/tiles?l=map&x={x}&y={y}&z={z}&scale=1&lang=ru_RU
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

//$DefURLBase = 'https://core-renderer-tiles.maps.yandex.net/tiles?l=map&scale=1&lang=ru_RU';
$DefURLBase = 'https://core-renderer-tiles.maps.yandex.net/tiles?l=map&scale=1&lang=ru_US&client_id=yandex-web-maps&experimental_use_metapoi=1&experimental_metapoi_combined_tiles=true&experimental_ranking_mode_name=default-web-ranking&experimental_mm_local_rubricpoi_min_zoom=13&experimental_mm_local_photopoi_min_zoom=13&experimental_mm_photopoi_min_zoom=13&experimental_mm_rubricpoi_min_zoom=13&ads=enabled&maptype=future_map';
$DefURLBase .= "&z=$z&x=$x&y=$y";
changeTORnode($getURLoptions['r']);
return array($DefURLBase,$opts);
}; // end function getURL
?>
