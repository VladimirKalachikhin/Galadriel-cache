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
global $globalProxy;
// http://www.sasgis.org/wikisasiya/doku.php/%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BF%D0%B0%D1%81%D0%BA%D0%B0%D0%BB%D1%8C_%D1%81%D0%BA%D1%80%D0%B8%D0%BF%D1%82%D0%BE%D0%B2
$VTimeStamp = time();    
//$VRequestUrl = 'https://backend.navionics.io/tile/get_key/Navionics_internalpurpose_00001/webapiv2.navionics.com?_=' . $VTimeStamp;
//$VRequestHeader = "Origin: https://webapiv2.navionics.com\r\n" . 'Referer: https://webapiv2.navionics.com/examples/4000_gNavionicsOverlayExample.html';
$VRequestUrl = 'http://backend.navionics.io/tile/get_key/Navionics_internalpurpose_00001/webapp.navionics.com?_=' . $VTimeStamp;
$VRequestHeader = "Origin: http://webapp.navionics.com/\r\n" . 'Referer: http://webapp.navionics.com/';

$opts = array(
	'http'=>array(
		'method'=>"GET",
		//'header'=> "User-Agent:Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)\r\n" . "$VRequestHeader\r\n"
		'header'=> "$VRequestHeader\r\n"
	)
);
if($globalProxy) { 	// глобальный прокси
	$opts['http']['proxy']=$globalProxy;
	$opts['http']['request_fulluri']=TRUE;
}
$context = stream_context_create($opts); 	// 
try {
	$VNavToken = file_get_contents($VRequestUrl, FALSE, $context); 	// 
	//echo "GetNavToken http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";
	//print_r($VNavToken);
	//error_log("fNavionics.php GetNavToken: NavToken: $VNavToken");
	if(!$VNavToken) { 	// не хочет отдавать, что делать?
		$VNavToken = ''; $VTimeStamp = 0;
		throw new Exception('No NavToken getting from server');
	}
}
catch (Exception $e) {
	$VNavToken = ''; $VTimeStamp = 0;
	error_log('fNavionics.php GetNavToken: NavToken fetch error: '.$e->getMessage());
}
//echo "<br>fNavionics VNavToken=$VNavToken;\n";
return array($VNavToken,$VTimeStamp);
} // end function GetNavToken
?>
