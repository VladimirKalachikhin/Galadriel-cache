<?php
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* By the http://wiki.openstreetmap.org/wiki/ProxySimplePHP
Берёт тайл из кеша и сразу отдаёт-показывает.
Потом, если надо - скачивает
Если полученный тайл ещё не показывали (он новый) - показываем.
*/
//$now = microtime(TRUE);
$path_parts = pathinfo(__FILE__); // определяем каталог скрипта
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта

require('params.php'); 	// пути и параметры

$x = intval($_REQUEST['x']);
$y = intval($_REQUEST['y']);
$z = intval($_REQUEST['z']);
$r = filter_var($_REQUEST['r'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$freshOnly = FALSE; 	 // показывать тайлы, даже если они протухли

if((!$x) OR (!$y) OR (!$z) OR (!$r)) {
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
	showTile($img,$mime_type,$content_encoding,$ext); 	// тайл есть, возможно, пустой - сначала покажем
	// потом, если надо, получим
	if((($z <= $maxZoom) AND ($z >= $minZoom)) AND ($ttl AND ($imgFileTime > 0))) { 	// если масштаб допустим, есть функция получения тайла, и нет в кэше или файл протух
		//error_log("No $r/$z/$x/$y tile exist?; Expired to ".(time()-filemtime($fileName)-$ttl)."sec. maxZoom=$maxZoom;");
		// тайл надо получать
		//exec("$phpCLIexec tilefromsource.php $fileName > /dev/null 2>&1 &"); 	// exec не будет ждать завершения
		// вместо получения - положим в очередь, кто-нибудь когда-нибудь получит
		$jobName = "$sourceName.$z"; 	// имя файла задания
		$umask = umask(0); 	// сменим на 0777 и запомним текущую
		file_put_contents("$jobsInWorkDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для загрузчика
		chmod("$jobsInWorkDir/$jobName",0777); 	// чтобы запуск от другого юзера
		umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		error_log("tiles.php: Create working job: $jobsInWorkDir/$jobName;");
		if(!glob("$jobsDir/*.slock")) { 	// если не запущено ни одного планировщика
			//error_log("Need scheduler for zoom $z");
			exec("$phpCLIexec loaderSched.php > /dev/null 2>&1 &"); 	// если запускать сам файл, ему нужны права
		}
	} 
}
else { 	// тайла нет, тайл надо получать
	exec("$phpCLIexec tilefromsource.php $fileName"); 	// exec будет ждать завершения
	// покажем тайл
	$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша
	showTile($img,$mime_type,$content_encoding,$ext); 	//покажем тайл
}
END:
ob_clean(); 	// очистим, если что попало в буфер
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
