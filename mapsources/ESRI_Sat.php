<?php
$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, один год
//$ttl = 86400*30*6; //cache timeout in seconds время, через которое тайл считается протухшим, пол-года
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'jpg'; 	// tile image type/extension
$ContentType = 'image/jpeg'; 	// if content type differ then file extension
$minZoom = 0;
$maxZoom = 19;
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
//'3df36e26' 	// чистый голубой квадрат
);
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* 
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=ESRI_Sat
*/
$url = 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/';
$url .= "/".$z."/".$y."/".$x;
return $url;
}
EOFU;
?>
