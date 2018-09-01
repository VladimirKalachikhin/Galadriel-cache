<?php
//$ttl = 86400*30*12*2; //cache timeout in seconds время, через которое тайл считается протухшим, 2 год
$ttl = 86400*30*6; //cache timeout in seconds время, через которое тайл считается протухшим, 1/2 год
//$ttl = 0; //cache timeout in seconds время, через которое тайл считается протухшим
$ext = 'png'; 	// tile image type/extension
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
	'b2451042', 	// пустой серый тайл с логотипом
	'97aa1cbf', 	// кривая надпись про недоступность
	'3629b525',		// нормальная надпись про недоступность
	'1c2bea2b',		// другая нормальная надпись про недоступность
	'bbe4324b',		// другая кривая надпись про недоступность
	'd7c8e677',
	'd79fc9ac',
	'e152e6d7',
	'a2054605',
	'84e1d0fd',
	'9e888860',
	'cda5dd37',
	'b187eebe',
	'192e10ea',
	'096a8efa',
	'0d3c286f',
	'367e4613',
	'42ed66ac',
	'85392d61',
	'592f75e8',
	'5607efe7',
	'277b9ee1',
	'0940c426' 		// пустой тайл
);
$minZoom = 15;
$maxZoom = 19;
require_once('fNavionics.php'); 	// дополнительные функции, необходимые для получения тайла
$functionGetURL = <<<'EOFU'
require_once('fNavionics.php'); 	// дополнительные функции, необходимые для получения тайла

function getURL($zoom,$x,$y) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
http://192.168.10.10/tileproxy/tiles.php?z=16&x=37995&y=18581&r=navionics_sonarchart_layer

*/

$DefURLBase='http://backend.navionics.io/tile/';
$RequestHead='Referer: https://webapiv2.navionics.com/examples/4000_gNavionicsOverlayExample.html';
$tokenTimeOut = 12*60*60; // сек. - время, через которое токен считается протухшим, и надо запрашивать снова
//******************************************************************************
// LAYERS parameter: config_a_b_c
//    a = 1 for depth in meters, 2 for depth in feet, 3 for fathoms
//    b = 10.00: for 10.00 m safety depth (beginning of blue coloring) (unit equal to that set by a)
//    c = 0 for pristine Navionics charts, 1 for Sonar Charts
//
// TRANSPARENT parameter: 
//    FALSE for non-layer
//    TRUE for layer
//
// UGC parameter: 
//    FALSE for pristine Navionics charts
//    TRUE for additinal user-generated content icons
//******************************************************************************
$cReqParams = 'LAYERS=config_1_10.00_1&TRANSPARENT=TRUE&UGC=FALSE';

list($VNavToken,$VTimeStamp) = $_SESSION['NavionicsToken'];
//echo "Before: VNavToken=$VNavToken;\nVTimeStamp=$VTimeStamp;<br>\n";
//echo "Должен протухнуть в " . time() . "-$tokenTimeOut<br>\n" ;
if((time()-$tokenTimeOut) > $VTimeStamp) { 	// токен протух
	$VNavToken = GetNavToken(); 	// ../fNavionics.php получим новый токен и время его получения
	$_SESSION['NavionicsToken'] = $VNavToken; 	// сохраним токен
	list($VNavToken,$VTimeStamp) = $VNavToken;
}
if(!$VNavToken) return ''; 	// обломаемся, если нет токена. Считаем, что процедура получения тайла понимает пустую строку вместо uri
$ResultURL = $DefURLBase . "$zoom/$x/$y" . "?$cReqParams" . "&navtoken=$VNavToken";
//echo "ResultURL=$ResultURL; <br>\n";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent:Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)\r\n" . "$RequestHead\r\n"
	)
);
return array($ResultURL,$opts);
}
EOFU;
?>
