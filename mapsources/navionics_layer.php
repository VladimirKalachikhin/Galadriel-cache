<?php
//$ttl = 86400*30*12*2; //cache timeout in seconds время, через которое тайл считается протухшим, 2 год
$ttl = 86400*30*6; //cache timeout in seconds время, через которое тайл считается протухшим, 1/2 год
//$ttl = 0; //cache timeout in seconds время, через которое тайл считается протухшим
$ext = 'png'; 	// tile image type/extension
$on403 = 'skip'; 	// что делать, если Forbidden: skip, wait - default
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями	'0940c426' пустой тайл - не мусор! Иначе пустые файлы будут скачиваться снова и снова, а их много.

$trash = array(
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
	'5bee5657',
	'50b52340',
	'692b3e4e',
	'0728ad40',
	'79fb55ba',
	'8b8b309b',
	'fbb9396a',
	'ac7eb08c',
	'6e6b4e0d',
	'd9f2998f',
	'0fbd6fa5',
	'5e1f7018',
	'9e89506d',
	'a01ccc39',
	'417c93ed',
	'c9eb16f8',
	'8ce7f158',
	'e64a995e',
	'307c4966',
	'd87aee55' 	// запрет Navionics в Дании
);

$minZoom = 10;
$maxZoom = 19;

$functionGetURL = <<<'EOFU'
require_once('fNavionics.php'); 	// дополнительные функции, необходимые для получения тайла

function getURL($zoom,$x,$y) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=navionics_layer

*/

global $tileCacheDir, $r, $on403; 	// from params.php, from tiles.php, from self
$tokenFileName = "$tileCacheDir/$r/navtoken";

$DefURLBase='http://backend.navionics.io/tile/';
//$RequestHead='Referer: https://webapiv2.navionics.com/examples/4000_gNavionicsOverlayExample.html\r\nUser-Agent:Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)';
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
$cReqParams = 'LAYERS=config_1_10.00_0&TRANSPARENT=TRUE&UGC=FALSE';

list($VNavToken,$VTimeStamp) = $_SESSION['NavionicsToken'];
if(!$VNavToken) { 	// нет сессии - протухла или клиент cli, или клиент не умеет печеньки
	list($VNavToken,$VTimeStamp) = unserialize(@file_get_contents($tokenFileName));
}
//echo "Before: VNavToken=$VNavToken;\nVTimeStamp=$VTimeStamp;<br>\n";
//error_log( "Before: VNavToken=$VNavToken;\nVTimeStamp=$VTimeStamp;<br>\n");
//echo "Должен протухнуть в " . time() . "-$tokenTimeOut<br>\n" ;
if((time()-$tokenTimeOut) > $VTimeStamp) { 	//  токена нет ($VTimeStamp==0) или токен протух
	$VNavToken = GetNavToken(); 	// ../fNavionics.php получим новый токен и время его получения
	$_SESSION['NavionicsToken'] = $VNavToken; 	// сохраним токен
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	file_put_contents($tokenFileName, serialize($VNavToken)); 	// запишем файл с токеном
	@chmod($bannedSourcesFileName,0777); 	// чтобы при запуске от другого юзера была возаможность 
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	list($VNavToken,$VTimeStamp) = $VNavToken;
}
if(!$VNavToken) $on403='wait'; 	//  нет токена. Запрос тайла окончится 403, на что мы скажем wait
$ResultURL = $DefURLBase . "$zoom/$x/$y" . "?$cReqParams" . "&navtoken=$VNavToken";
//echo "ResultURL=$ResultURL; <br>\n";
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
