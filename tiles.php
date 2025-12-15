<?php
session_start();	// возможно, какие-то пользовательские функции используют сессию. Сама система - нет.
ob_start(); 	// попробуем перехватить любой вывод скрипта
/*
Version 3.1.0
История History:
3.1.0	- store to database in the MBTiles format
3.0.0	- new API, multilayer and complex maps and etc.
2.10.0	- map's function PrepareTileFile for tilefromsource.php with support for uploading more them one tile
2.9.0	- map's $bounds support
*/
// Для обеспечения работы после отдачи тайла клиенту и скачивания тайла, которого нет
set_time_limit(0); 			// Cause we are clever and don't want the rest of the script to be bound by a timeout. Set to zero so no time limit is imposed from here on out.
ignore_user_abort(true); 	// чтобы выполнение не прекратилось после разрыва соединения
/*
Если тайл === null - возвращается 404 Not Found
Если тайл === false - возвращается 400 Bad Request
Карта покрытия называется просто COVER, а покрытие чего - указывается в $r

Понимает следующие параметры в $requestOptions:
$requestOptions['prepareTileImg'] = bool - 	применять ли функцию $prepareTileImgBeforeReturn к возвращаемому изображению.
 												whether to use the function $prepareTileImgBeforeReturn to then output image
$requestOptions['r'] = mapName - заменять в команде $mapTiles url шаблон {map} на указанную строку или на имя карты.
									whether to replace the {map} template in the $mapTiles url command to the specified string or to the map name.
$requestOptions['layer'] = (int)N - будет запускаться фоновое скачивание слоя №N, а не всей карты, как mapName_N.Zoom
									Will be started the background download of the layer $N, not the entire map, as mapName_N.Zoom

Функции:
showTile($img,$mime_type='',$content_encoding='',$ext='')	Отдаёт тайл. Считается, что только эта функция что-то показывает клиенту
createJob($mapSourcesName,$z,$x,$y,$oneOnly=FALSE)	Создаёт задание для загрузчика для скачивания карты с именем $mapSourcesName
*/
//$now = microtime(true);
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта

require('params.php'); 	// пути и параметры (без указания пути оно сперва ищет в include_path, а он не обязан начинаться с .)
require 'mapsourcesVariablesList.php';	// умолчальные переменные и функции описания карты, полный комплект
require 'fCommon.php';	// функции, используемые более чем в одном крипте
require('fIRun.php'); 	// 
require 'fTilesStorage.php';	// стандартные функции получения тайла из локального источника

//	Каталог описаний источников карт, в файловой системе
$mapSourcesDir = 'mapsources'; 	// map sources directory, in filesystem.

$x = filter_var($_REQUEST['x'],FILTER_SANITIZE_NUMBER_INT);
$y = filter_var($_REQUEST['y'],FILTER_SANITIZE_NUMBER_INT);
$z = filter_var($_REQUEST['z'],FILTER_SANITIZE_NUMBER_INT);
$r = filter_var($_REQUEST['r'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$requestOptions = $_REQUEST['options'];	// оно уже urldecode
$requestOptions = json_decode($requestOptions,true);
if(is_string($requestOptions)){
	$requestOptions = trim($requestOptions);
	if($requestOptions) $requestOptions = array($requestOptions=>true);
	else $requestOptions = array();
};

if(($x==='') OR ($y==='') OR ($z==='') OR ($r==='') OR ($x===false) OR ($y===false) OR ($z===false) OR ($r===false)) {
	//echo "Incorrect tile info: $r/$z/$x/$y<br>\n";		
	header("X-Debug: Incorrect tile info: $r/$z/$x/$y");
	showTile(false); 	// 400 Bad Request
	return;
};
$x = intval($x);
list($y,$requestExt) = explode('.',$y);
$y = intval($y); 
if(!$ext) $ext = trim($requestExt);	// Расширение из конфига имеет преимущество!
$z = intval($z);
//echo "z=$z; x=$x; y=$y; requestExt=$requestExt; r=$r;<br>\n";
//echo "requestOptions:<pre>"; print_r($requestOptions); echo "</pre><br>\n";

if(!(@include "$mapSourcesDir/$r.php")){
	//echo "X-Debug: Map description file not found<br>\n";
	header("X-Debug: Map description file not found");
	showTile(false);	// 400 Bad Request
	return;
};

if(($z<$minZoom)or($z>$maxZoom)){
	//echo "X-Debug: Request is out of zoom<br>\n";
	header("X-Debug: Request is out of zoom");
	showTile(false); // 400 Bad Request
	return;
};

// Концептуально тут нужно проверить попадание запрошенного тайла в границы карты.
// Но это довольно затратно, поэтому не будем.
// Штатная функция получения тайла проверяет это только после обнаружения отсутствия тайла в кеше.

// Получение тайла.
// Штатной функцией или пользовательской. Если штатной - то из файловой системы
// с возможным получением от источника
// Штатная функция может вернуть признак needToRetrieve, для
// фонового получения тайла из источника. Фоновое получение надо запускать после отдачи
// тайла клиенту, ибо это долго.
$img = false; $needToRetrieve = false;
if($requestOptions['r']) $r = $requestOptions['r'];

//echo "tiles.php [] z=$z; x=$x; y=$y; r=$r; requestOptions:<pre>"; print_r($requestOptions); echo "</pre><br>\n";
extract($getTile($r,$z,$x,$y,$requestOptions),EXTR_IF_EXISTS);	// оно возвращает array('img'=>,'ContentType'=>)
//file_put_contents('savedTiles',"tiles.php - $r,$z,$x,$y; needToRetrieve=$needToRetrieve;\n",FILE_APPEND);	
//echo "tiles.php из хранилища: ".strlen($img)." байт<br>\n";

if($requestOptions['prepareTileImg']) {	// обработка картинки, если таковая указана в описании источника
	//echo "tiles.php before prepared: ".strlen($img)."<br>\n";
	$prepared = $prepareTileImgBeforeReturn($img);
	if(@$prepared['img']) extract($prepared);	// может быть изменён $ContentType, $ext, etc.
	//echo "tiles.php after prepared: ".strlen($img)."<br>\n";
	
	unset($prepared);
};

//echo "X-Debug: The tile was received in ".(microtime(true)-$now)." sec.\n"; 
//header("X-Debug: The tile was received in ".(microtime(true)-$now)." sec."); 
showTile($img,$mime_type,$content_encoding,$ext);	// отдадим тайл клиенту

if($getURL){	// если есть, чем скачивать
	if($requestOptions['layer']) $r .= '__'.addslashes($requestOptions['layer']);
	if($needToRetrieve){	// Нужно запустить фоновое скачивание тайла из источника 
		createJob($r,$z,$x,$y,TRUE);	// скачать только этот тайл
	};
	if(($img !== false) and ($z >= $aheadLoadStartZoom)){	// Нужно запустить фоновое скачивание тайла из источника 
		createJob($r,$z,$x,$y); 	// положим в очередь
	};
};

ob_end_clean(); 			// очистим, если что попало в буфер
return;



// Функции

function showTile($img,$mime_type='',$content_encoding='',$ext='') {
/*
Отдаёт тайл. Считается, что только эта функция что-то показывает клиенту
https://gist.github.com/bubba-h57/32593b2b970366d24be7
*/
//return;	// FOR TEST
//apache_setenv('no-gzip', '1'); 	// отключить сжатие вывода
ob_end_clean(); 			// очистим, если что попало в буфер
ob_start();
header("Connection: close"); 	// Tell the client to close connection
// тайла могло не быть в кеше, и его не удалось получить или его попортила функция prepareTile
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
	//$exp_gmt = gmdate("D, d M Y H:i:s", time() + 60*60) ." GMT"; 	// Тайл будет стопудово кешироваться браузером 1 час
	//header("Expired: " . $exp_gmt);
	//$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($fileName)) ." GMT"; 	// слишком долго?
	//header("Last-Modified: " . $mod_gmt);
	//header("Cache-Control: public, max-age=3600"); 	// Тайл будет стопудово кешироваться браузером 1 час
	if(($ext == 'pbf') or ($mime_type == 'application/x-protobuf')){
		header ("Content-Type: application/x-protobuf");
	}
	elseif($mime_type) header ("Content-Type: $mime_type");
	elseif($ext) header ("Content-Type: image/$ext");
	else{
		$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
		$mime_type = finfo_buffer($file_info,$img);
		if($mime_type) header ("Content-Type: $mime_type");
	}
	if($content_encoding) header ("Content-encoding: $content_encoding");
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


function createJob($mapSourcesName,$z,$x,$y,$oneOnly=FALSE) {
/* Создаёт задание для загрузчика
для скачивания карты с именем $mapSourcesName
начиная с тайла $z,$x,$y
или если $oneOnly -- только этот тайл
*/
global $jobsDir,$jobsInWorkDir,$phpCLIexec; 	// из params.php и параметров карты
$jobName = "$mapSourcesName.$z"; 	// имя файла задания
$umask = umask(0); 	// сменим на 0777 и запомним текущую
//error_log("tiles.php createJob: Update loader job $jobsInWorkDir/$jobName by $x,$y");
if($oneOnly) { 	// нужно загрузить только один тайл
	// нужно положить задание в каталог заданий для загрузчика, есть там такое, или нет
	file_put_contents("$jobsInWorkDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для загрузчика
	@chmod("$jobsDir/$jobName",0666); 	// чтобы запуск от другого юзера
}
else { 	// нужно загрузить всё
	// нужно добавить к заданию для загрузчика, если там такое есть
	// ибо если просто дать его планировщику, то оно исчезнет
	if(file_exists("$jobsInWorkDir/$jobName")){
		file_put_contents("$jobsInWorkDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для загрузчика
		@chmod("$jobsDir/$jobName",0666); 	// чтобы запуск от другого юзера
	};
	// дадим задание планировщику
	file_put_contents("$jobsDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для планировщика. Понимаем, что этот тайл не будет скачан, если задание существует и скачивается. Будут скачаны только тайлы следующего масштаба. Но выше мы положили то же самое в задание для загрузчика.
	@chmod("$jobsDir/$jobName",0666); 	// чтобы запуск от другого юзера
};
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
if(!IRun('loaderSched')){ 	// если не запущено ни одного планировщика
	error_log("tiles.php createJob: Need scheduler for zoom $z; phpCLIexec=$phpCLIexec;");
	//echo "tiles.php createJob: Need scheduler for zoom $z; phpCLIexec=$phpCLIexec;<br>\n";
	//exec("$phpCLIexec loaderSched.php > /dev/null 2>&1 &",$output,$result); 	// асинхронно.
	exec("$phpCLIexec loaderSched.php > /dev/null &",$output,$result); 	// асинхронно. Так будет виден вывод loaderSched
	//echo "[createJob] result=$result; output:<pre>";print_r($output);echo "</pre><br>\n";
};
}; // end function createJob


?>
