<?php
$humanName = array('ru'=>'Карта OpenStreetMap','en'=>'OpenStreetMap');
$ttl = 86400*365; // 1 year cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$noTileReTry=60*60; 	
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 19;
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'57eeeb20');	// to source check; tile number and CRC32b hash
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
	'bb659f2e',	// App is not following the tile usage policy, казлы.
	'17d1e948',
	'ee225378'
);
$getURLoptions['r'] = pathinfo(__FILE__, PATHINFO_FILENAME);	// $getURLoptions будет передан в $getURL

$prepareTileImgBeforeReturn = function ($img){
/* цвет моря - 171,211,223,
Если заменить эти цвета на прозрачный, можно наложить эту карту на непрозрачные морские
*/
if(!$img) return array('img'=>$img);
$img = setColorsTransparent($img,array(
	array(171,211,223),
	array(181,202,212),
),null,false);
return array('img'=>$img);
}; // end function prepareTileImg


$getURL = function ($z,$x,$y,$options=array()) {
$server = 'https://tile.openstreetmap.org';

//$userAgent = randomUserAgent();
//$userAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0';
//$referer = "Referer: https://www.openstreetmap.org/\r\n";
//$RequestHead = "Connection: keep-alive\r\n";
//$RequestHead .= "Accept: image/avif,image/webp,image/png,image/svg+xml,image/*;q=0.8,*/*;q=0.5\r\nAccept-Encoding: gzip, deflate, br, zstd\r\n";

$url = "$server/$z/$x/$y.png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		//'header'=>"User-Agent: $userAgent\r\n$referer$RequestHead",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		//'protocol_version'=>'1.1',
		//'request_fulluri'=>TRUE
	)
);
//changeTORnode($getURLoptions['r']);
return array($url,$opts);
};
?>
