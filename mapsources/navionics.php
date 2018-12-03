<?php
$ttl = 86400*30*12*5; //cache timeout in seconds время, через которое тайл считается протухшим, 5 год
//$ttl = 0; //cache timeout in seconds время, через которое тайл считается протухшим
$ext = 'png'; 	// tile image type/extension
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
	'7a64e130',
	'b2451042', 	// пустой серый тайл с логотипом
	'97aa1cbf', 	// кривая надпись про недоступность
	'3629b525', 	// нормальная надпись про недоступность
	'd87aee55' 	// запрет Navionics в Дании
);
$minZoom = 4;
$maxZoom = 19;

$functionGetURL = <<<'EOFU'
require_once('fNavionics.php'); 	// дополнительные функции, необходимые для получения тайла
function getURL($zoom,$x,$y) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=navionics_layer

*/

$DefURLBase='http://backend.navionics.io/tile/';
//$RequestHead='Referer: https://webapiv2.navionics.com/examples/4000_gNavionicsOverlayExample.html\r\nUser-Agent:Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)\r\n';
$RequestHead='Referer: http://webapp.navionics.com/';
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
$cReqParams = 'LAYERS=config_1_10.00_0&TRANSPARENT=FALSE&UGC=FALSE';

list($VNavToken,$VTimeStamp) = $_SESSION['NavionicsToken'];
//echo "Before: VNavToken=$VNavToken;\nVTimeStamp=$VTimeStamp;<br>\n";
//error_log( "Before: VNavToken=$VNavToken;\nVTimeStamp=$VTimeStamp;<br>\n");
//echo "Должен протухнуть в " . time() . "-$tokenTimeOut<br>\n" ;
if((time()-$tokenTimeOut) > $VTimeStamp) { 	//  токена ($VTimeStamp==0) нет или токен протух
	$VNavToken = GetNavToken(); 	// ../fNavionics.php получим новый токен и время его получения
	$_SESSION['NavionicsToken'] = $VNavToken; 	// сохраним токен
	list($VNavToken,$VTimeStamp) = $VNavToken;
}
if(!$VNavToken) return ''; 	// обломаемся, если нет токена. Считаем, что процедура получения тайла понимает пустую строку вместо uri
$ResultURL = $DefURLBase . "$zoom/$x/$y" . "?$cReqParams" . "&navtoken=$VNavToken";
//echo "ResultURL=$ResultURL; <br>\n";
//error_log("ResultURL=$ResultURL;");
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=> "$RequestHead\r\n"
	)
);
return array($ResultURL,$opts);
}
EOFU;
?>
