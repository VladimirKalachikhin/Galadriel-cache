<?php
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* By the http://wiki.openstreetmap.org/wiki/ProxySimplePHP
Берёт тайл из кеша и сразу отдаёт-показывает.
Потом, если надо - скачивает
Если полученный тайл ещё не показывали (он новый) - показываем.
*/
//$now = microtime(TRUE);
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта
require_once('fcommon.php');

require('params.php'); 	// пути и параметры

$x = intval($_REQUEST['x']);
$y = intval($_REQUEST['y']);
$z = intval($_REQUEST['z']);
$r = filter_var($_REQUEST['r'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$freshOnly = FALSE; 	 // показывать тайлы, даже если они протухли

if(!$x OR !$y OR !$z OR !$r) {
	showTile(NULL); 	// покажем 404
	error_log("Incorrect tile info: $r/$z/$x/$y");		
	goto END;
}
// определимся с источником карты
$sourcePath = explode('/',$r); 	// $r может быть с путём до конкретного кеша, однако - никогда абсолютным
$sourceName = $sourcePath[0];
unset($sourcePath[0]);
$sourcePath = implode('/',$sourcePath); 	// склеим дополнительный путь обратно, если его не было - будет пустая строка. Но этот путь больше нигде не используется?
require("$mapSourcesDir/$sourceName.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
// возьмём тайл
$path_parts = pathinfo($y); // 
if(!$path_parts['extension']) {
	if($ext) $y = "$y.$ext"; 	// в конфиге источника указано расширение
	else $y = "$y.png";
}
$fileName = "$tileCacheDir/$r/$z/$x/$y"; 	// из кэша
//echo "file=$fileName; <br>\n";
$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша, возможно, за приделами разрешённых масштабов
if($img!==FALSE) { 	// тайл есть
	$imgFileTime = time()-filemtime($fileName)-$ttl; 	// прожитое тайлом время сверх положенного
	if($ttl AND ($imgFileTime > 0) AND $freshOnly) $img=FALSE; 	// тайл протух, но указано протухшие тайлы не показывать
	//error_log("Get      $r/$z/$x/$y.$ext : ".strlen($img)." bytes from cache");		
	showTile($img,$ext); 	// тайл есть, возможно, пустой - сначала покажем
	//error_log("showTile $r/$z/$x/$y.$ext from cache");		
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
	showTile($img,$ext); 	//покажем тайл
}
END:
ob_clean(); 	// очистим, если что попало в буфер
?>
