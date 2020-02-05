<?php
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* By the http://wiki.openstreetmap.org/wiki/ProxySimplePHP
Берёт тайл из кеша и сразу отдаёт-показывает.
Потом, если надо - скачивает
Если полученный тайл ещё не показывали (он новый) - показываем.
В CLI тайл не показываем, только скачиваем.

$img - существующий тайл из кэша
$newimg - полученный тайл. === FALSE - получить не удалось по каким-то причинам, === NULL - тайла нет
*/
//$now = microtime(TRUE);
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта
require_once('fcommon.php');

require('params.php'); 	// пути и параметры

if(@$argv) { 	// cli
	$runCLI = TRUE;
	$options = getopt("z:x:y:r::");
	//print_r($options);
	$x = intval($options['x']);
	$y = intval($options['y']);
	$z = intval($options['z']);
	$r = strip_tags($options['r']);
	$forceFresh = TRUE; 	// retrieve tile from source if it expired
}
else {	// http
	$runCLI = FALSE;
	$x = intval($_REQUEST['x']);
	$y = intval($_REQUEST['y']);
	$z = intval($_REQUEST['z']);
	$r = strip_tags($_REQUEST['r']);
}
if(!$x OR !$y OR !$z OR !$r) {
	if(!$runCLI) showTile(NULL); 	// покажем 404
	error_log("Incorrect tile info: $r/$z/$x/$y");		
	goto END;
}
// возьмём тайл
$fileName = "$tileCacheDir/$r/$z/$x/$y.$ext"; 	// из кэша
//echo "file=$fileName; <br>\n";
// определимся с источником карты
$sourcePath = explode('/',$r); 	// $r может быть с путём до конкретного кеша, однако - никогда абсолютным
$sourceName = $sourcePath[0];
unset($sourcePath[0]);
$sourcePath = implode('/',$sourcePath); 	// склеим путь обратно, если его не было - будет пустая строка
require_once("$mapSourcesDir/$sourceName.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша, возможно, за приделами разрешённых масштабов
if($img!==FALSE) { 	// тайл есть
	$imgFileTime = time()-filemtime($fileName)-$ttl; 	// прожитое тайлом время сверх положенного
	if($ttl AND ($imgFileTime > 0) AND $freshOnly) $img=FALSE; 	// тайл протух, но указано протухшие тайлы не показывать
	//error_log("Get      $r/$z/$x/$y.$ext : ".strlen($img)." bytes from cache");		
	if(!$runCLI) 	{ 	// тайл есть, возможно, пустой, спросили из браузера
		showTile($img,$ext); 	// сначала покажем
		//error_log("showTile $r/$z/$x/$y.$ext from cache");		
	}
	// потом, если надо, получим
	if((($z <= $maxZoom) AND ($z >= $minZoom)) AND ($ttl AND ($imgFileTime > 0))) { 	// если масштаб допустим, есть функция получения тайла, и нет в кэше или файл протух
		//error_log("No $r/$z/$x/$y tile exist?; Expired to ".(time()-filemtime($fileName)-$ttl)."sec. maxZoom=$maxZoom;");
		// тайл надо получать
		exec("php tilefromsource.php $fileName > /dev/null 2>&1 &"); 	// exec не будет ждать завершения
	} 
}
else { 	// тайла нет, тайл надо получать
	exec("php tilefromsource.php $fileName"); 	// exec будет ждать завершения
	// покажем тайл
	$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша
	if(!$runCLI) showTile($img,$ext); 	//покажем тайл
}
END:
if($runCLI) {
	if($img===FALSE) fwrite(STDOUT, '1'); 	// тайла не было и он не был получен
	else fwrite(STDOUT, '0');
}
ob_clean(); 	// очистим, если что попало в буфер
?>
