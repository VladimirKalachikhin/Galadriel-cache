<?php
$ttl = 86400*30*12*3; //cache timeout in seconds время, через которое тайл считается протухшим, три год
//$ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 4;
$maxZoom = 18;
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* Алгоритм получения ссылки на тайл получен автором путем реверсинжиниринга. С клинингом, да
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=fonecta
*/
$url='http://kartta.fonecta.fi/oym?f=m&ft=png_nauti_256&key=FO1349G5NGDTJ52H913SFRK63928';
if(($z<=18) AND ($z>=5)) {
	$x = $x - ((1 << $z)/2);
	$y = ((1 << $z)/2)-1-$y;
	$z = 18-$z;
}
$url .= "&x=$x&y=$y&z=$z";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"Referer: https://www.fonecta.fi/kartat?l=NAU\r\n"
	)
);
return array($url,$opts);
}
EOFU;
?>
