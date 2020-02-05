<?php
/*
tileNum2degree - Tile numbers to lon./lat. left top corner
tileNum2mercOrd - Tile numbers to linear coordinates left top corner on mercator ellipsoidal
merc_x - Долготу в линейную координату x, Меркатор на эллипсоиде
merc_y - Широту в линейную координату y, Меркатор на эллипсоиде
nextZoom - Возвращает четыре номера тайлов следующего (большего) масштаба
*/
function tileNum2degree($zoom,$xtile,$ytile) {
/* Tile numbers to lon./lat. left top corner
// http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
*/
$n = pow(2, $zoom);
$lon_deg = $xtile / $n * 360.0 - 180.0;
$lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));
return array('lon'=>$lon_deg,'lat'=>$lat_deg);
}

function tileNum2mercOrd($zoom,$xtile,$ytile,$r_major=6378137.000,$r_minor=6356752.3142) {
/* Меркатор на эллипсоиде
Tile numbers to linear coordinates left top corner on mercator ellipsoidal
*/
$deg = tileNum2degree($zoom,$xtile,$ytile);
$lon_deg = $deg['lon'];
$lat_deg = $deg['lat'];
//return array('x'=>round(merc_x($lon_deg),10),'y'=>round(merc_y($lat_deg),10));
return array('x'=>merc_x($lon_deg,$r_major),'y'=>merc_y($lat_deg,$r_major,$r_minor));
}

function merc_x($lon,$r_major=6378137.000) {
/* Меркатор на эллипсоиде
Долготу в линейную координату x
// http://wiki.openstreetmap.org/wiki/Mercator#PHP_implementation
*/
return $r_major * deg2rad($lon);
}

function merc_y($lat,$r_major=6378137.000,$r_minor=6356752.3142) {
/* Меркатор на эллипсоиде
Широту в линейную координату y
// http://wiki.openstreetmap.org/wiki/Mercator#PHP_implementation
*/
	if ($lat > 89.5) $lat = 89.5;
	if ($lat < -89.5) $lat = -89.5;
    $temp = $r_minor / $r_major;
	$es = 1.0 - ($temp * $temp);
    $eccent = sqrt($es);
    $phi = deg2rad($lat);
    $sinphi = sin($phi);
    $con = $eccent * $sinphi;
    $com = 0.5 * $eccent;
	$con = pow((1.0-$con)/(1.0+$con), $com);
	$ts = tan(0.5 * ((M_PI*0.5) - $phi))/$con;
    $y = - $r_major * log($ts);
    return $y;
}

function nextZoom($xy){
/* Возвращает четыре номера тайлов следующего (большего) масштаба
https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Resolution_and_Scale
Получает массив номера тайла (x,y)
*/
$nextZoom[0] = array(2*$xy[0],2*$xy[1]);	// левый верхний тайл
$nextZoom[1] = array(2*$xy[0]+1,2*$xy[1]);	// правый верхний тайл
$nextZoom[2] = array(2*$xy[0],2*$xy[1]+1);	// левый нижний тайл
$nextZoom[3] = array(2*$xy[0]+1,2*$xy[1]+1);	// правый нижнй тайл
return $nextZoom;
} // end function nextZoom

function quickFilePutContents($fileName,$content) {
/**/
$tmpFileName = tempnam('','');
echo "$tmpFileName \n";
file_put_contents($tmpFileName,$content);
rename($tmpFileName,$fileName);
}

function showTile($tile,$ext='') {
/*
Отдаёт тайл. Считается, что только эта функция что-то показывает клиенту
https://gist.github.com/bubba-h57/32593b2b970366d24be7
*/
global $runCLI;

if($runCLI) return; 	// не будем отдавать картинку в cli

//apache_setenv('no-gzip', '1'); 	// отключить сжатие вывода
set_time_limit(0); 			// Cause we are clever and don't want the rest of the script to be bound by a timeout. Set to zero so no time limit is imposed from here on out.
ignore_user_abort(true); 	// чтобы выполнение не прекратилось после разрыва соединения
ob_end_clean(); 			// очистим, если что попало в буфер
ob_start();
header("Connection: close"); 	// Tell the client to close connection
header("Content-Encoding: none");
if($tile) { 	// тайла могло не быть в кеше, и его не удалось получить
	$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
	$mime_type = finfo_buffer($file_info,$tile);
	$exp_gmt = gmdate("D, d M Y H:i:s", time() + 60*60) ." GMT"; 	// Тайл будет стопудово кешироваться браузером 1 час
	header("Expired: " . $exp_gmt);
	//$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($fileName)) ." GMT"; 	// слишком долго?
	//header("Last-Modified: " . $mod_gmt);
	header("Cache-Control: public, max-age=3600"); 	// Тайл будет стопудово кешироваться браузером 1 час
	if($mime_type) header ("Content-Type: $mime_type");
	else header ("Content-Type: image/$ext");
}
else {
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

function doBann($r) {
/* Банит источник */
global $bannedSources, $runCLI, $bannedSourcesFileName, $tries, $http_response_header;
//error_log("newimg=$newimg;");
//error_log(print_r($http_response_header,TRUE));
//error_log("doBann: bannedSources ".print_r($bannedSources,TRUE));

$curr_time = time();
$bannedSources[$r] = $curr_time; 	// отметим проблемы с источником
if($runCLI) { 	// если спрашивали из загрузчика
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	file_put_contents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
	//quickFilePutContents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
	@chmod($bannedSourcesFileName,0777); 	// чтобы при запуске от другого юзера была возаможность 
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
}
else { 	// спрашивают из браузера
	$_SESSION['bannedSources'] = $bannedSources; 	// 
}
//error_log("doBann: bannedSources ".print_r($bannedSources,TRUE));
error_log("fcommon.php doBann: Trying # $tries: $r banned at ".gmdate("D, d M Y H:i:s", $curr_time)."!");
} // end function doBann


?>
