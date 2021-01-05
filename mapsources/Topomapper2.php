<?php
/*
*/
$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, один год
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'jpg'; 	// tile image type/extension
$ContentType = 'image/jpeg'; 	// if content type differ then file extension
$minZoom = 1;
$maxZoom = 13;
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
);
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* 
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=ESRI_Sat
*/
$url = 'http://88.99.52.155/cgi-bin/tapp/tilecache.py/1.0.0/topomapper_v2';
$url .= "/".$z."/".$x."/".$y;
return $url;
}
EOFU;
?>
