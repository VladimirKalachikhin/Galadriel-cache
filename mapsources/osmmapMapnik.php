<?php
$ttl = 86400*365; // 1 year cache timeout in seconds время, через которое тайл считается протухшим
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 19;
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
$server = array();
$server[] = 'a.tile.openstreetmap.org';
$server[] = 'b.tile.openstreetmap.org';
$server[] = 'c.tile.openstreetmap.org';

$url = 'http://'.$server[array_rand($server)];
$url .= "/".$z."/".$x."/".$y.".png";
return $url;
}
EOFU;
?>
