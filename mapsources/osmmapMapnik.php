<?php
$humanName = array('ru'=>'Карта OpenStreetMap','en'=>'OpenStreetMap');
$ttl = 86400*365; // 1 year cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 19;
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'2010870b');	// to source check; tile number and CRC32b hash

$getURLoptions['r'] = pathinfo(__FILE__, PATHINFO_FILENAME);	// $getURLoptions будет передан в $getURL


$getURL = function ($z,$x,$y,$options=array()) {
$server = 'https://tile.openstreetmap.org';

$userAgent = randomUserAgent();
//$userAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0';
$referer = "Referer: https://www.openstreetmap.org/\r\n";
//$RequestHead = "Connection: keep-alive\r\n";
$RequestHead .= "Accept: image/avif,image/webp,image/png,image/svg+xml,image/*;q=0.8,*/*;q=0.5\r\nAccept-Encoding: gzip, deflate, br, zstd\r\n";

$url = "$server/$z/$x/$y.png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n$referer$RequestHead",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		//'protocol_version'=>'1.1',
		//'request_fulluri'=>TRUE
	)
);
changeTORnode($getURLoptions['r']);
return array($url,$opts);
};
?>
