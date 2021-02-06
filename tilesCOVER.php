<?php
ob_start(); 	// попробуем перехватить любой вывод скрипта
$path_parts = pathinfo(__FILE__); // определяем каталог скрипта
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта

require('params.php'); 	// пути и параметры

$x = intval($_REQUEST['x']);
$y = filter_var($_REQUEST['y'],FILTER_SANITIZE_URL); 	// 123456.png
$z = intval($_REQUEST['z']);
$r = filter_var($_REQUEST['r'],FILTER_SANITIZE_FULL_SPECIAL_CHARS); 	// имя карты, покрытие которой надо получить, без _COVER

//$x=618; $y=321; $z=10; 
//$x=143; $y=74; $z=8; 
//$r='OpenTopoMap'; 	// имя карты, но может быть с путём к подкартам

if((!$x) OR (!$y) OR (!$z) OR (!$r)) {
	goto END;
}

$mapAddPath = strstr($r,'/'); 	// путь к подкартам
$r = substr($r,0,strlen($r)-strlen($mapAddPath)); 	// имя карты

require("$mapSourcesDir/$r.php"); 	// параметры карты
if($z+8>$maxZoom) goto END; 	// нет смысла считать покрытие тайлов, которых заведомо нет
$path_parts = pathinfo($y); // 
$y = $path_parts['filename'];
// Расширение из конфига имеет преимущество!
if(!$ext) $ext = $path_parts['extension']; 	// в конфиге источника не указано расширение -- используем из запроса
if(!$ext) $ext='png'; 	// совсем нет -- умолчальное

$img = imagecreatetruecolor(256,256); 	// картинка пустая
imagesavealpha($img,TRUE);
//imagealphablending($img,TRUE);

$yesColor = imagecolorallocatealpha($img,1,1,1,80);
$noColor = imagecolorallocatealpha($img,0,0,0,127); 	// цвет, обозначающий, что нет
$bigZoomColor = imagecolorallocatealpha($img,0,0,255,112); 	// 

//imagecolortransparent($img,$noColor); 	// этот цвет будет прозрачным
imagefill($img, 0, 0, $noColor); 	// закрасим весь тайл

//$testColor = imagecolorallocate($img,255,0,255); 	// 
//imagerectangle($img,0,0,255,255,$testColor);

$bigZ = $loaderMaxZoom-$z; 	//
if($bigZ<8){	// будем показывать тайлы масштаба $loaderMaxZoom при более крупных, чем +8 масштабах просмотра
	// левый верхний тайл масштаба 16
	$tiles = 2**$bigZ; 	// тайлов по координате 16 масштаба в тайле данного масштаба
	$squareSize = 256/$tiles; 	// пикселей в изображении тайла 16 масштаба
	$coverX = $tiles*$x; 	
	$coverY = $tiles*$y;
	$coverZ = $loaderMaxZoom; 	
	//echo "tiles=$tiles; coverX=$coverX; coverY=$coverY; coverZ=$coverZ;\n";
	for($ix=$coverX;$ix<($coverX+$tiles);$ix++){
		for($jy=$coverY;$jy<($coverY+$tiles);$jy++){
			$tileName = "$tileCacheDir/$r$mapAddPath/$coverZ/$ix/$jy.$ext";
			$tileSize = @filesize($tileName);
			//echo "$tileName $tileSize\n";
			//echo (($ix-$coverX)*$squareSize).",".(($jy-$coverY)*$squareSize)." ".(($ix-$coverX)*$squareSize+$squareSize).",".(($jy-$coverY)*$squareSize+$squareSize)."\n";
			if($tileSize){
				imagefilledrectangle($img,($ix-$coverX)*$squareSize,($jy-$coverY)*$squareSize,($ix-$coverX)*$squareSize+$squareSize,($jy-$coverY)*$squareSize+$squareSize,$bigZoomColor);
			}
		}
	}
}

// левый верхний тайл масштаба +8
$coverX = 2**8*$x; 	
$coverY = 2**8*$y;
$coverZ = $z+8; 	
//echo "$coverZ: $coverX / $coverY\n";
for($ix=$coverX;$ix<$coverX+256;$ix++){
	for($jy=$coverY;$jy<$coverY+256;$jy++){
		$tileName = "$tileCacheDir/$r$mapAddPath/$coverZ/$ix/$jy.$ext";
		$tileSize = @filesize($tileName);
		//echo "$tileName $tileSize\n";
		//echo ($ix-$coverX).",".($jy-$coverY)."\n";
		if($tileSize){
			imagesetpixel($img,$ix-$coverX,$jy-$coverY,$yesColor);
		}
	}
}

ob_clean(); 	// очистим, если что попало в буфер
header ("Content-Type: image/png");
imagepng($img);
END:
return;




function showTile($tile,$mime_type='',$content_encoding='',$ext='') {
/*
Отдаёт тайл. Считается, что только эта функция что-то показывает клиенту
https://gist.github.com/bubba-h57/32593b2b970366d24be7
*/
//apache_setenv('no-gzip', '1'); 	// отключить сжатие вывода
set_time_limit(0); 			// Cause we are clever and don't want the rest of the script to be bound by a timeout. Set to zero so no time limit is imposed from here on out.
ignore_user_abort(true); 	// чтобы выполнение не прекратилось после разрыва соединения
ob_end_clean(); 			// очистим, если что попало в буфер
ob_start();
header("Connection: close"); 	// Tell the client to close connection
if($tile) { 	// тайла могло не быть в кеше, и его не удалось получить
	if(!$mime_type) {
		$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
		$mime_type = finfo_buffer($file_info,$tile);
	}
	elseif(($mime_type == 'application/x-protobuf') and (!$content_encoding)) {
		$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
		$file_type = finfo_buffer($file_info,$tile);
		//header("X-Debug: $file_type");
		if($file_type == 'application/x-gzip') $content_encoding = 'gzip';
	}
	//$exp_gmt = gmdate("D, d M Y H:i:s", time() + 60*60) ." GMT"; 	// Тайл будет стопудово кешироваться браузером 1 час
	//header("Expired: " . $exp_gmt);
	//$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($fileName)) ." GMT"; 	// слишком долго?
	//header("Last-Modified: " . $mod_gmt);
	//header("Cache-Control: public, max-age=3600"); 	// Тайл будет стопудово кешироваться браузером 1 час
	if($mime_type) header ("Content-Type: $mime_type");
	elseif($ext) header ("Content-Type: image/$ext");
	if($content_encoding) header ("Content-encoding: $content_encoding");
}
else {
	header("X-Debug: Not found if no tile");
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
}
echo $tile; 	// теперь в output buffer только тайл
$content_lenght = ob_get_length(); 	// возьмём его размер
header("Content-Length: $content_lenght"); 	// завершающий header
ob_end_flush(); 	// отправляем тело - собственно картинку и прекращаем буферизацию
@ob_flush();
flush(); 		// Force php-output-cache to flush to browser.
ob_start(); 	// попробуем перехватить любой вывод скрипта
}


?>
