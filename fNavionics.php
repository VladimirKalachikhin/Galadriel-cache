<?php
/* Функции, необходимые для работы с источниками карт Navionics
На основе соответствующих функций из файлов GetUrlScript.txt источников карт Navionics SAS.Planet http://www.sasgis.org/

GetNavToken() Запрашивает новый токен для карт
*/

function GetNavToken() {
/* Запрашивает новый токен для карт 
Возвращает массив, 
первый элемнт которого - токен или пустая строка, если что-то пошло не так
второй - unixtimestamp получения токена
*/
// http://www.sasgis.org/wikisasiya/doku.php/%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BF%D0%B0%D1%81%D0%BA%D0%B0%D0%BB%D1%8C_%D1%81%D0%BA%D1%80%D0%B8%D0%BF%D1%82%D0%BE%D0%B2

$VTimeStamp = time();    
$VRequestUrl = 'https://backend.navionics.io/tile/get_key/Navionics_internalpurpose_00001/webapiv2.navionics.com?_=' . $VTimeStamp;
$VRequestHeader = "Origin: https://webapiv2.navionics.com\r\n" . 'Referer: https://webapiv2.navionics.com/examples/4000_gNavionicsOverlayExample.html';

$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent:Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)\r\n" . "$VRequestHeader\r\n"
	)
);
$context = stream_context_create($opts); 	// 
try {
	$VNavToken = file_get_contents($VRequestUrl, FALSE, $context); 	// 
	if($VNavToken === FALSE) { 	// не хочет отдавать, что делать?
		$VNavToken = '';
	}
}
catch (Exception $e) {
	$VNavToken = '';
}
//echo "<br>fNavionics VNavToken=$VNavToken;\n";
return array($VNavToken,$VTimeStamp);
} // end function GetNavToken
?>
