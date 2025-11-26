<?php
ob_start(); 	// попробуем перехватить любой вывод скрипта
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта

require('params.php'); 	// пути и параметры
require 'mapsourcesVariablesList.php';	// умолчальные переменные и функции описания карты, полный комплект
require "$mapSourcesDir/COVER.php";	// не очень важно, есть описание карты, или нет

//	Каталог описаний источников карт, в файловой системе
$mapSourcesDir = 'mapsources'; 	// map sources directory, in filesystem.

$x = filter_var($_REQUEST['x'],FILTER_SANITIZE_NUMBER_INT);
$y = filter_var($_REQUEST['y'],FILTER_SANITIZE_URL); 	// 123456.png
$z = filter_var($_REQUEST['z'],FILTER_SANITIZE_NUMBER_INT);
$r = filter_var($_REQUEST['r'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$requestOptions = filter_var($_REQUEST['options'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);	// оно уже urldecode
$requestOptions = json_decode($requestOptions,true);

if(($x==='') OR ($y==='') OR ($z==='') OR ($r==='') OR ($x===false) OR ($y===false) OR ($z===false) OR ($r===false)) {
	//echo "Incorrect tile info: $r/$z/$x/$y<br>\n";		
	header("X-Debug: Incorrect tile info: $r/$z/$x/$y");
	showTile(false); 	// 400 Bad Request
	return;
};
$x = intval($x);
$y = intval($y); 
$z = intval($z);
//echo "z=$z; x=$x; y=$y; r=$r;<br>\n";
//echo "requestOptions:"; print_r($requestOptions); echo "\n";
//file_put_contents('savedTiles',"Требуется тайл $r/$z/$x/$y;\n",FILE_APPEND | LOCK_EX);	
$showTHENloading = 0;	// ничего не показывать 

$coverFileName = "$tileCacheDir/COVER/$r/$z/$x/$y.png"; 	// из кэша
$imgFileTime = @filemtime($coverFileName); 	// файла может не быть
//echo "tiles.php: $r/$z/$x/$y tile exist:$imgFileTime, and expired to ".(time()-(filemtime($coverFileName)+$ttl))."sec. и имеет дату модификации ".date('d.m.Y H:i',$imgFileTime)."<br>\n";
if($imgFileTime) { 	// файл есть
	if(($imgFileTime+$ttl) < time()) { 	// файл протух.
		$showTHENloading = 2; 	//сперва скачивать, потом показывать
	}
	else $showTHENloading = 3;	// только показывать
}
else{	// файла нет
	$showTHENloading = 2; 	//сперва скачивать, потом показывать
};

if($showTHENloading == 3){	// только показывать
	$img = @file_get_contents($coverFileName); 	// берём тайл из кеша
	if($img === false){	// файла нет
		//echo "X-Debug: ERROR to get COVER tile from cache.<br>\n";
		header("X-Debug: ERROR to get COVER tile from cache.");
		showTile(false); // 400 Bad Request
		return;
	};
	showTile($img);
	return;
};

// Делаем тайл покрытия
require 'fTilesStorage.php';	// стандартные функции получения тайла из локального источника
@include "$mapSourcesDir/$r.php";	// не очень важно, есть описание карты, или нет

if(($z<$minZoom)or($z>$maxZoom)){
	//echo "X-Debug: Request is out of zoom<br>\n";
	header("X-Debug: Request is out of zoom");
	showTile(false); // 400 Bad Request
	return;
};

$img = imagecreatetruecolor(256,256); 	// картинка пустая
imagesavealpha($img,TRUE);
//imagealphablending($img,TRUE);

$yesColor = imagecolorallocatealpha($img,1,1,1,80);
$noColor = imagecolorallocatealpha($img,0,0,0,127); 	// цвет, обозначающий, что нет, прозрачный
$bigZoomColor = imagecolorallocatealpha($img,0,0,255,112); 	// 

//imagecolortransparent($img,$noColor); 	// этот цвет будет прозрачным
imagefill($img, 0, 0, $noColor); 	// закрасим весь тайл

$bigZ = $loaderMaxZoom-$z; 	//
if($bigZ<8){	// будем показывать тайлы покрытия масштаба $loaderMaxZoom при более крупных, чем +8 масштабах просмотра (вместе с соответствующим масштабом тайлов покрытия)
	// левый верхний тайл масштаба 16
	$tiles = 2**$bigZ; 	// тайлов по координате 16 масштаба в тайле данного масштаба
	$squareSize = 256/$tiles; 	// пикселей в изображении тайла 16 масштаба
	$coverX = $tiles*$x; 	
	$coverY = $tiles*$y;
	$coverZ = $loaderMaxZoom; 	
	//echo "tiles=$tiles; coverX=$coverX; coverY=$coverY; coverZ=$coverZ;\n";
	for($ix=$coverX;$ix<($coverX+$tiles);$ix++){
		for($jy=$coverY;$jy<($coverY+$tiles);$jy++){
			$checkonly=false;
			extract($getTile($r,$coverZ,$ix,$jy,array('checkonly'=>true)),EXTR_IF_EXISTS);	// оно возвращает array('img'=>,'ContentType'=>)
			//echo (($ix-$coverX)*$squareSize).",".(($jy-$coverY)*$squareSize)." ".(($ix-$coverX)*$squareSize+$squareSize).",".(($jy-$coverY)*$squareSize+$squareSize)."\n";
			if($checkonly){
				imagefilledrectangle($img,($ix-$coverX)*$squareSize,($jy-$coverY)*$squareSize,($ix-$coverX)*$squareSize+$squareSize,($jy-$coverY)*$squareSize+$squareSize,$bigZoomColor);
			};
		};
	};
};

// левый верхний тайл масштаба +8
//file_put_contents('savedTiles',"Создаём тайл $r/$z/$x/$y;\n",FILE_APPEND | LOCK_EX);	
$coverX = 2**8*$x; 	
$coverY = 2**8*$y;
$coverZ = $z+8; 	
//echo "$coverZ: $coverX / $coverY\n";
for($ix=$coverX;$ix<$coverX+256;$ix++){
	for($jy=$coverY;$jy<$coverY+256;$jy++){
		$checkonly=false;
		//echo "tile: $coverZ/$ix/$jy; checkonly=$checkonly;<br>\n";
		extract($getTile($r,$coverZ,$ix,$jy,array('checkonly'=>true)),EXTR_IF_EXISTS);	// оно возвращает array('img'=>,'ContentType'=>)
		if($checkonly){
			//echo "tile: $coverZ/$ix/$jy; checkonly=$checkonly;<br>\n";
			//file_put_contents('savedTiles',"$r/$z/$x/$y: $coverZ/$ix/$jy; checkonly=$checkonly;\n",FILE_APPEND | LOCK_EX);	
			imagesetpixel($img,$ix-$coverX,$jy-$coverY,$yesColor);
		};
	};
};
//return;
// А тепрь из gd image сделаем нормальную картику
ob_start();	// оно может быть вложенным
ob_clean();
imagepng($img);
imagedestroy($img);
$img = ob_get_contents();
ob_end_clean();

showTile($img);	// показываем тайл
// потом сохраняем
$umask = umask(0); 	// сменим на 0777 и запомним текущую
@mkdir(dirname($coverFileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
$res = file_put_contents($coverFileName,$img,LOCK_EX);
//file_put_contents('savedTiles',"Saved COVER tile ".strlen($img)." bytes to $coverFileName with res=$res; \n\n",FILE_APPEND | LOCK_EX);	
if($res===false){
	error_log("ERROR saved COVER tile $coverFileName");
};
chmod($coverFileName,0666); 	// чтобы при запуске от другого юзера была возможность заменить тайл, когда он протухнет
//error_log("Saved COVER tile".strlen($img)." bytes to $coverFileName");	
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера

ob_end_clean();
exit;

function showTile($img) {
/*
*/
//return;
ob_end_clean(); 			// очистим, если что попало в буфер
ob_start();
header("Connection: close"); 	// Tell the client to close connection
if($img === false){	// тайла нет по какой-то аварийной причине
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
	header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
}
elseif($img === null){	// тайла нет потому что
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
}
else { 	
	header ("Content-Type:  image/png");
};
echo $img; 	// теперь в output buffer только тайл
$content_lenght = ob_get_length(); 	// возьмём его размер
header("Content-Length: $content_lenght"); 	// завершающий header
header("Access-Control-Allow-Origin: *"); 	// эта пурга их какой-то горбатой безопасности, смысл которой я так и не уловил
//header("Access-Control-Expose-Headers: *"); 	// эта пурга должна позволить показывать заголовки запросов, но они и так показываются?
ob_end_flush(); 	// отправляем тело - собственно картинку и прекращаем буферизацию
@ob_flush();
flush(); 		// Force php-output-cache to flush to browser.
ob_start(); 	// попробуем перехватить любой вывод скрипта
}; // end function showTile


?>
