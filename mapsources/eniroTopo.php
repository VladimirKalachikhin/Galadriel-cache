<?php
$ttl = 86400*30*12; //cache timeout in seconds время, через которое тайл считается протухшим. 86400 - сутки
//$ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 4;
$maxZoom = 18;
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
);
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* 
Algorithm getted from https://github.com/osmandapp/Osmand/issues/5818 and other
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=eniroTopo
*/
$url='https://map.eniro.com/geowebcache/service/tms1.0.0/map/';
//$url='https://map.eniro.com/geowebcache/service/tms1.0.0/nautical/';
$y = ((1 << $z) - 1 - $y);
$url .= "$z/$x/$y".".png";
return $url;
}
EOFU;
?>
