<?php
$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, один год
//$ttl = 86400*30*6; //cache timeout in seconds время, через которое тайл считается протухшим, пол-года
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'jpg'; 	// tile image type/extension
$ContentType = 'image/jpeg'; 	// if content type differ then file extension
$minZoom = 0;
$maxZoom = 19;
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=ESRI_Topo_Maps
*/
$url = 'http://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/';
$url .= "/".$z."/".$y."/".$x;
return $url;
}
EOFU;
?>
