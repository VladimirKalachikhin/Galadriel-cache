<?php
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* By the http://wiki.openstreetmap.org/wiki/ProxySimplePHP
Берёт тайл из кеша и сразу отдаёт-показывает.
Потом, если надо - скачивает
Если полученный тайл ещё не показывали (он новый) - показываем.
*/
//$nowTime = microtime(TRUE);
chdir(__DIR__); // задаем директорию выполнение скрипта

$freshOnly = FALSE; 	 // показывать тайлы, даже если они протухли
require('fcommon.php');
require('params.php'); 	// пути и параметры (без указания пути оно сперва ищет в include_path, а он не обязан начинаться с .)

$x = filter_var($_REQUEST['x'],FILTER_SANITIZE_NUMBER_INT);
$y = filter_var($_REQUEST['y'],FILTER_SANITIZE_URL); 	// 123456.png
$z = filter_var($_REQUEST['z'],FILTER_SANITIZE_NUMBER_INT);
$r = filter_var($_REQUEST['r'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if(($x===false) OR ($y===false) OR ($z===false) OR ($r===false)) {
	showTile(NULL); 	// покажем 404
	error_log("Incorrect tile info: $r/$z/$x/$y");		
	goto END;
}
$x = intval($x);
$y = intval($y);
$z = intval($z);
// 		FOR TEST
//$x=19;$y=11;$z=5;$r="world-coastline";
//$x=19;$y=11;$z=5;$r="osmmapMapnik";
//$x=4822;$y=6161;$z=14;$r="NOAA_USA_ENC";
//// 	FOR TEST
// определимся с источником карты
$sourcePath = explode('/',$r); 	// $r может быть с путём до конкретного кеша, однако - никогда абсолютным
$sourceName = $sourcePath[0];
if($pos=strpos($sourceName,'_COVER')) { 	// нужно показать покрытие, а не саму карту
	require("$mapSourcesDir/common_COVER"); 	// файл, описывающий источник тайлов покрытия, используемые ниже переменные - оттуда.
}
else {
	$res=include("$mapSourcesDir/$sourceName.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
	if(!$res){
		showTile(NULL); 	// покажем 404
		error_log("Incorrect map name: $sourceName");		
		goto END;
	};
}
//error_log("function_exists('getTile'):".function_exists('getTile'));
if($functionGetTileFile){	// у карты есть собственная функция получения тайла
	eval($functionGetTileFile);	// создаём функцию получения данных
	extract(getTileFile($r,$z,$x,$y),EXTR_OVERWRITE);	// выполняем функцию получения даных. Обычно она возвращает массив ['img',value], и extract присваивает $img=value
}
else {
	// возьмём тайл
	$path_parts = pathinfo($y); // 
	$y = $path_parts['filename'];
	// Расширение из конфига имеет преимущество!
	if(!$ext) $ext = $path_parts['extension']; 	// в конфиге источника не указано расширение -- используем из запроса
	if(!$ext) $ext='png'; 	// совсем нет -- умолчальное
	$fileName = "$tileCacheDir/$r/$z/$x/$y.$ext"; 	// из кэша
	//echo "file=$fileName; <br>\n"; 
	/*
	Нужно понять -- тайл сначала показывать, потом скачивать, или сначала скачивать, а потом показывать
	если тайла нет -- если есть, чем скачивать - сперва скачивать, потом показывать, иначе 404
	если тайл есть, он не нулевой длины, и не протух -- просто показать
	если тайл есть, он не нулевой длины, и протух, и указано протухшие не показывать -- скачать, если есть, чем скачивать, потом показать, иначе 404
	если тайл есть, он не нулевой длины, и протух, и не указано протухшие не показывать -- показать, потом скачать, если есть чем, иначе - просто показать
	если файл есть, но нулевой длины -- использовать специальное время протухания 
	*/
	/* $showTHENloading
	0; 	// только показывать 
	1; 	// сперва показывать, потом скачивать 
	2; 	//сперва скачивать, потом показывать
	*/
	$showTHENloading = 0;	// только показывать 

	//clearstatcache();
	$imgFileTime = @filemtime($fileName); 	// файла может не быть
	//echo "tiles.php: $r/$z/$x/$y tile exist:$imgFileTime, and expired to ".(time()-(filemtime($fileName)+$ttl))."sec. и имеет дату модификации ".date('d.m.Y H:i',$imgFileTime)."<br>\n";
	if($imgFileTime) { 	// файл есть
		if(($imgFileTime+$ttl) < time()) { 	// файл протух. Таким образом, файлы нулевой длины могут протухнуть раньше, но не позже.
			//error_log("tiles.php: $r/$z/$x/$y tile expired to ".(time()-(filemtime($fileName)+$ttl))."sec. freshOnly=$freshOnly; maxZoom=$maxZoom;");
			if($freshOnly) { 	// протухшие не показывать
				if($functionGetURL)	$showTHENloading = 2; 	//сперва скачивать, потом показывать
				else {
					showTile(NULL); 	// покажем 404
					goto END;
				};
			}
			else { 	// протухшие показывать
				$img = file_get_contents($fileName); 	// берём тайл из кеша, возможно, за приделами разрешённых масштабов
				//error_log("tiles.php: Get rotten tile $r/$z/$x/$y.$ext : ".strlen($img)." bytes from cache");		
				if($functionGetURL) $showTHENloading = 1; 	// сперва показывать, потом скачивать
			}
		}
		else { 	// файл свежий
			$img = file_get_contents($fileName); 	// берём тайл из кеша, возможно, за приделами разрешённых масштабов
			//error_log("tiles.php: Get fresh tile $r/$z/$x/$y.$ext : ".strlen($img)." bytes from cache");		
			if(!$img) { 	// файл нулевой длины
			 	if($noTileReTry) $ttl= $noTileReTry; 	// если указан специальный срок протухания для файла нулевой длины -- им обозначается перманентная проблема скачивания
				if(($imgFileTime+$ttl) < time()) { 	// файл протух
					if($freshOnly) { 	// протухшие не показывать
						if($functionGetURL) $showTHENloading = 2; 	//сперва скачивать, потом показывать
					}
					else {
						if($functionGetURL) $showTHENloading = 1; 	// сперва показывать, потом скачивать
					};
				};
			};
		};
	}
	elseif($functionGetURL) { 	// файла нет, но в описании карты указано, где взять
		//error_log("tiles.php: No $r/$z/$x/$y tile exist?");
		if(checkInBounds($z,$x,$y,$bounds)){	// тайл вообще должен быть?
			$showTHENloading = 2; 	//сперва скачивать, потом показывать
		}
		else{	// тайла и не должно быть
			showTile(NULL); 	// покажем 404
			goto END;
		};
	}
	else{	// файла нет, и негде взять
		showTile(NULL); 	// покажем 404
		goto END;
	};
};

// Так или иначе - тайл получен или не получен, и решение, что делать - выработано
if($functionPrepareTileImg) eval($functionPrepareTileImg);	// определим функцию обработки картинки
//error_log("tiles.php: $r/$z/$x/$y showTHENloading=$showTHENloading;");
//echo "tiles.php: $r/$z/$x/$y showTHENloading=$showTHENloading;<br>\n";
//$file_info = finfo_open(FILEINFO_MIME); 	// подготовимся к определению mime-type
//$file_type = finfo_buffer($file_info,$img);
//echo "$file_type\n";

switch($showTHENloading){
case 1: 	// сперва показывать, потом скачивать 
	showTile($img,$ContentType,$content_encoding,$ext); 	// тайл есть, возможно, пустой
	//exec("$phpCLIexec tilefromsource.php $fileName > /dev/null 2>&1 &"); 	// exec не будет ждать завершения
	// вместо получения - положим в очередь
	if($z >= $aheadLoadStartZoom) createJob($sourceName,$z,$x,$y); 	//echo "поставим задание на получение всех нижележащих тайлов, если этот тайл удачно скачался<br>\n";
	else createJob($sourceName,$z,$x,$y,TRUE);	// скачать только этот тайл
	break;
case 2: 	//echo "сперва скачивать, потом показывать<br>\n";
	$execStr = "$phpCLIexec tilefromsource.php $fileName";
	if(thisRun($execStr)) sleep(1); 	// Предотвращает множественную загрузку одного тайла одновременно, если у proxy больше одного клиента. Не сильно тормозит?
	else exec($execStr); 	// exec будет ждать завершения
	// покажем тайл
	$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша, скачивание могло плохо кончиться
	//if($img) echo "Тайл скачался <br>\n";	else echo "Тайл не скачался <br>\n";
	showTile($img,$ContentType,$content_encoding,$ext); 	//покажем тайл

	// Опережающее скачивание при показе - должно помочь с крупными масштабами
	if($img and ($z >= $aheadLoadStartZoom)) { 	//echo "поставим задание на получение всех нижележащих тайлов, если этот тайл удачно скачался<br>\n";
		//echo "$sourceName,$z,$x,$y<br>\n";
		createJob($sourceName,$z,$x,$y); 	// скачать начиная с этого тайла
	}
	break;
default: 	// только показывать 
	showTile($img,$ContentType,$content_encoding,$ext); 	// тайл есть, возможно, пустой
}
END:
ob_clean(); 	// очистим, если что попало в буфер
return;

function showTile($img,$mime_type='',$content_encoding='',$ext='') {
/*
Отдаёт тайл. Считается, что только эта функция что-то показывает клиенту
https://gist.github.com/bubba-h57/32593b2b970366d24be7
*/
//global $nowTime;
//return;
//apache_setenv('no-gzip', '1'); 	// отключить сжатие вывода
set_time_limit(0); 			// Cause we are clever and don't want the rest of the script to be bound by a timeout. Set to zero so no time limit is imposed from here on out.
ignore_user_abort(true); 	// чтобы выполнение не прекратилось после разрыва соединения
ob_end_clean(); 			// очистим, если что попало в буфер
ob_start();
header("Connection: close"); 	// Tell the client to close connection
if($img) { 	// тайла могло не быть в кеше, и его не удалось получить или его попортила функция prepareTile
	if(function_exists('prepareTileImg')) {	// обработка картинки, если таковая указана в описании источника
		$prepared = prepareTileImg($img);
		if($prepared['img']) extract($prepared);
		unset($prepared);
	}
	//$exp_gmt = gmdate("D, d M Y H:i:s", time() + 60*60) ." GMT"; 	// Тайл будет стопудово кешироваться браузером 1 час
	//header("Expired: " . $exp_gmt);
	//$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($fileName)) ." GMT"; 	// слишком долго?
	//header("Last-Modified: " . $mod_gmt);
	//header("Cache-Control: public, max-age=3600"); 	// Тайл будет стопудово кешироваться браузером 1 час
	if(($ext == 'pbf') or ($mime_type == 'application/x-protobuf')){
		if(!$content_encoding){
			$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
			$file_type = finfo_buffer($file_info,$img);
			//header("X-Debug: $file_type");
			if($file_type == 'application/x-gzip') $content_encoding = 'gzip';
		}
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
}
else {
	header("X-Debug: Not found if no tile");
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
}
echo $img; 	// теперь в output buffer только тайл
$content_lenght = ob_get_length(); 	// возьмём его размер
header("Content-Length: $content_lenght"); 	// завершающий header
header("Access-Control-Allow-Origin: *"); 	// эта пурга их какой-то горбатой безопасности, смысл которой я так и не уловил
//header("Access-Control-Expose-Headers: *"); 	// эта пурга должна позволить показывать заголовки запросов, но они и так показываются?
//header("X-CacheTiming: ".($nowTime-microtime(TRUE)));
ob_end_flush(); 	// отправляем тело - собственно картинку и прекращаем буферизацию
@ob_flush();
flush(); 		// Force php-output-cache to flush to browser.
ob_start(); 	// попробуем перехватить любой вывод скрипта
}

function createJob($mapSourcesName,$z,$x,$y,$oneOnly=FALSE) {
/* Создаёт задание для загрузчика
для скачивания карты с именем $mapSourcesName
начиная с тайла $z,$x,$y
или если $oneOnly -- только этот тайл
*/
global $jobsDir,$jobsInWorkDir,$phpCLIexec,$aheadLoadStartZoom,$loaderMaxZoom,$maxZoom; 	// из params.php и параметров карты
//echo "mapSourcesName=$mapSourcesName; z=$z; jobsDir=$jobsDir; phpCLIexec=$phpCLIexec, aheadLoadStartZoom=$aheadLoadStartZoom, loaderMaxZoom=$loaderMaxZoom\n";
if(($z > $loaderMaxZoom) OR ($z < $aheadLoadStartZoom)) $oneOnly=TRUE; 	// масштаб вне указанного для планировщика
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
	}
	// дадим задание планировщику
	file_put_contents("$jobsDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для планировщика. Понимаем, что этот тайл не будет скачан, если задание существует и скачивается. Будут скачаны только тайлы следующего масштаба. Но выше мы положили то же самое в задание для загрузчика.
	@chmod("$jobsDir/$jobName",0666); 	// чтобы запуск от другого юзера
}
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
if(!glob("$jobsDir/*.slock")) { 	// если не запущено ни одного планировщика
	//error_log("tiles.php createJob: Need scheduler for zoom $z; phpCLIexec=$phpCLIexec;");
	exec("$phpCLIexec loaderSched.php > /dev/null 2>&1 &"); 	// асинхронно. если запускать сам файл, ему нужны права
}
} // end function createJob

function thisRun($exec) {
/**/
$pid = getmypid();
exec("ps -A w | grep '$exec'",$psList);
if(!$psList) exec("ps w | grep '$exec'",$psList); 	// for OpenWRT. For others -- let's hope so all run from one user
//print_r($psList); //
$run = FALSE;
foreach($psList as $str) {
	//error_log("$str;\n");
	if(strpos($str,(string)$pid)!==FALSE) continue;
	if(strpos($str,'grep')!==FALSE) continue;
	if(strpos($str,$exec)!==FALSE){
		$run=TRUE;
		break;
	}
}
//error_log("tiles.php thisRun:$run;\n");
return $run;
}

?>
