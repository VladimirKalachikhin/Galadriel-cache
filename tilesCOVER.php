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
// http://192.168.10.10/tileproxy/tilesCOVER.php?z=5&x=19&y=8&r=C-MAP

if((!$x) OR (!$y) OR (!$z) OR (!$r)) {
	goto END;
}

$mapAddPath = strstr($r,'/'); 	// путь к подкартам
$r = substr($r,0,strlen($r)-strlen($mapAddPath)); 	// имя карты

//echo "x=$x; y=$y; z=$z; r=$r;<br>\n";
require("$mapSourcesDir/$r.php"); 	// параметры карты
if($z+8>$maxZoom) goto END; 	// нет смысла считать покрытие тайлов, которых заведомо нет
$path_parts = pathinfo($y); // 
$y = $path_parts['filename'];
// Расширение из конфига имеет преимущество!
if(!$ext) $ext = $path_parts['extension']; 	// в конфиге источника не указано расширение -- используем из запроса
if(!$ext) $ext='png'; 	// совсем нет -- умолчальное

//echo "ext=$ext;<br>\n";
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
		//$tileSize = file_exists($tileName);
		//echo "$tileName $tileSize<br>\n";
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

?>
