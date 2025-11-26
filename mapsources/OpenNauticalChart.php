<?php
/* http://opennauticalchart.org/
https://wiki.opennauticalchart.org/index.php?title=Main_Page
*/
$humanName = array('ru'=>'Морской слой OpenNautical','en'=>'OpenNautical chart');
//$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
$ttl = 86400*30*3; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 18;
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями. '1556c7bd' чистый голубой квадрат 'c7b10d34' чистый голубой квадрат - не мусор! Иначе такие тайлы будут скачиваться снова и снова, а их много.
$trash = array(
);
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19906,9851,'6d3f3777');	// to source check; tile number and CRC32b hash

$getURL = function ($z,$x,$y) {
/* 
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=OpenTopoMap
*/
//error_log("OpenNauticalChart $z,$x,$y");

//$userAgent = randomUserAgent();
//$RequestHead='Referer: http://opennauticalchart.org/';

$url = 'http://t1.openseamap.org/seamark';
$url .= "/".$z."/".$x."/".$y.".png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		//'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		'request_fulluri'=>TRUE
	)
);
//print_r($opts);
// set it if you hawe Tor as proxy, and want change exit node every $tilesPerNode try. https://stackoverflow.com/questions/1969958/how-to-change-the-tor-exit-node-programmatically-to-get-a-new-ip
// tor MUST have in torrc: ControlPort 9051 without authentication: CookieAuthentication 0 and #HashedControlPassword
// Alternative: set own port, config tor password by tor --hash-password my_password and stay password in `echo authenticate '\"\"'`
changeTORnode($getURLoptions['OpenNauticalChart']);
return array($url,$opts);
};
?>
