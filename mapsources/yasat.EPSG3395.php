<?php
$ttl = 86400*365*2; //cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'jpg'; 	// tile image type/extension
$ContentType = 'image/jpeg'; 	// if content type differ then file extension
$EPSG=3395;
$minZoom = 8;
$maxZoom = 19;
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=yandex_sat
Карта в EPSG=3395 !!!! Меркатор на эллипсоиде
*/
$server = array();
$server[] = 'sat01';
$server[] = 'sat02';
$server[] = 'sat03';

$DefURLBase = 'http://'.$server[array_rand($server)] . '.maps.yandex.net/tiles?l=sat&';
$DefURLBase .= "z=$z&x=$x&y=$y&g=" . substr('Gagarin',0,rand(1,7));
return $DefURLBase;
}
EOFU;
?>
