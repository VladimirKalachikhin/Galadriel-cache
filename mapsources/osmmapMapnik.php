<?php
$humanName = array('ru'=>'Карта OpenStreetMap','en'=>'OpenStreetMap');
$ttl = 86400*365; // 1 year cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 19;
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'2010870b');	// to source check; tile number and CRC32b hash

$getURL = function ($z,$x,$y) {
$server = array();
$server[] = 'tile.openstreetmap.org';

$userAgent = randomUserAgent();
$RequestHead='Referer: http://openstreet.com';
//$RequestHead='';

$url = 'http://'.$server[array_rand($server)];
$url .= "/".$z."/".$x."/".$y.".png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		'request_fulluri'=>TRUE
	)
);
return array($url,$opts);
};
?>
