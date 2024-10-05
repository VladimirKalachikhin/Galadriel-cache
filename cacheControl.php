<?php
/* web - интерфейс для получения информации об имеющихся картах, управления загрузчикаом
и ещё для чего-нибудь.
Типа, API. Возвращает json.
*/
$usage='Usage:
	Получить список карт
cacheControl.php?getMapList		get maps list
return:
{
	"map_source_file_name": {
		"en": "Human-readable english map name or map_source_file_name",
		["ru": ...]
	}
}

	Получить сведения о карте из её файла описания:
cacheControl.php?getMapInfo=map_source_file_name	get map info
return:
{
    "ext": "...",
    "ContentType": "...",
    "epsg": "...",
    "minZoom": ?,
    "maxZoom": ?,
    "data": "...",
    "mapboxStyle": "..."
}

	Поставить задание на скачивание
cacheControl.php?loaderJob=map_source_file_name.zoom&xys=csv_of_XY[&infinitely] create & run tile loading job, 
return:
{
	"status": 0,
	"jobName": map_source_file_name.zoom
}

	Получить состояние заданий на скачивание
cacheControl.php?loaderStatusP[&stopLoader]|[&restartLoader][&infinitely]] 	get loader status
return:
{
	"loaderRun": scheduler_PID,
	"jobsInfo": {
		"map_source_file_name.zoom": %_of_complite
	}
}
';

ob_start(); 	// попробуем перехватить любой вывод скрипта
session_start();

chdir(__DIR__); // задаем директорию выполнение скрипта
require('params.php'); 	// пути и параметры (без указания пути оно сперва ищет в include_path, а он не обязан начинаться с .)

$result = null;
//$_REQUEST['getMapList'] = true;
if(array_key_exists('getMapList',$_REQUEST)){	
// Вернуть список имеющихся карт
	// Получаем список имён карт
	$mapsInfo = null;
	foreach(glob("$mapSourcesDir/*.php") as $name) {
		$mapName=explode('.php',end(explode('/',$name)))[0]; 	// basename не работает с неанглийскими буквами!!!!
		$humanName = array();
		include($name);
		if($humanName){	// из описания источника
			if(!$humanName['en']) $humanName['en'] = $mapName;
		}
		else $humanName['en'] = $mapName;
		$mapsInfo[$mapName] = $humanName;
	};
	$result = $mapsInfo;
}
elseif($mapName=filter_var($_REQUEST['getMapInfo'],FILTER_SANITIZE_URL)){	
// Вернуть сведения о конкретной карте
	if(strpos($mapName,'_COVER')) { 	// нужно показать покрытие, а не саму карту
		include("$mapSourcesDir/common_COVER"); 	// файл, описывающий источник тайлов покрытия, используемые ниже переменные - оттуда.
	}
	else include("$mapSourcesDir/$mapName.php");
	$mapInfo = array(
		'ext'=>$ext,
		'ContentType'=>$ContentType,
		'epsg'=>$EPSG, 
		'minZoom'=>$minZoom,
		'maxZoom'=>$maxZoom,
		'data'=>$data
	);
	if($bounds) $mapInfo['bounds'] = $bounds;
	if(($ext=='pbf' or $ContentType=='application/x-protobuf')and(file_exists("$mapSourcesDir/$mapName.json"))) $mapInfo['mapboxStyle'] = "$tileCacheServerPath/$mapSourcesDir/$mapName.json"; 	// путь в смысле web
	$result = $mapInfo;
}
elseif($jobName=filter_var($_REQUEST['loaderJob'],FILTER_SANITIZE_URL)){	
// Поставить задание на скачивание
	$XYs = $_REQUEST['xys'];
	//echo "XYs=$XYs; jobName=$jobName; <br>\n";
	//$jobName='OpenTopoMap.11';
	//$XYs="1189,569\n1190,569\n1191,569";
	if($jobName != 'restart') {
		$name_parts = pathinfo($jobName);	// pathinfo не работает с русскими буквами!
		//echo "name_parts:<pre>"; print_r($name_parts); echo "</pre>";
		if(!(is_numeric($name_parts['extension']) AND (intval($name_parts['extension']) <=20 AND intval($name_parts['extension']) >=0))) goto LOADERJOBEND; 	// расширение - не масштаб
		if(!is_file("$mapSourcesDir/".$name_parts['filename'].'.php')) goto LOADERJOBEND; 	// нет такого источника
		if(!$XYs) goto LOADERJOBEND; 	// нет собственно задания
		// Создадим задание
		$umask = umask(0); 	// сменим на 0777 и запомним текущую
		// нужно положить в каталог заданий для загрузчика, ибо аналогичное задание уже может выполняться, и если его дать планировщику, то оно исчезнет
		if(file_exists("$jobsInWorkDir/$jobName")){
			file_put_contents("$jobsInWorkDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для загрузчика
			@chmod("$jobsInWorkDir/$jobName",0666); 	// чтобы запуск от другого юзера
		}
		file_put_contents("$jobsDir/$jobName",$XYs,FILE_APPEND); 	// возможно, такое задание уже есть. Тогда, скорее всего, тайлы указанного масштаба не будут загружены, а будут загружены эти тайлы следующего масштаба. Не страшно.
		// Сохраним задание на всякий случай
		file_put_contents("$jobsDir/oldJobs/$jobName".'_'.gmdate("Y-m-d_Gis", time()),$XYs);
		//file_put_contents("$jobName",$XYs);
		@chmod("$jobsDir/$jobName",0666); 	// чтобы запуск от другого юзера
		umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	}

	// Запустим планировщик
	// Если эта штука вызывается для нескольких карт подряд, то просто при запуске планировщика
	// каждый его экземпляр видит, что запущены другие, и завершается. В результате не запускается ни один.
	// Поэтому будем запускать планировщик не чаще чем раз в секунд.
	$infinitely = '';
	if(array_key_exists('infinitely',$_REQUEST)) $infinitely = '--infinitely';
	$status = 0;
	//echo (time()-$_SESSION['loaderJobStartLoader'])." ";
	if((time()-$_SESSION['loaderJobStartLoader'])>3) {
		exec("$phpCLIexec loaderSched.php $infinitely > /dev/null 2>&1 &",$ret,$status); 	// если запускать сам файл, ему нужны права
		//exec("$phpCLIexec loaderSched.php > log_$jobName.txt 2>&1 &",$ret,$status); 	// если запускать сам файл, ему нужны права
		if($status==0)$_SESSION['loaderJobStartLoader'] = time();	// при успешном запуске
	};
	$result = array("status"=>$status,"jobName"=>$jobName);
	LOADERJOBEND:
}
elseif(array_key_exists('loaderStatus',$_REQUEST)){	
// Получить состояние заданий на скачивание
	if(array_key_exists('restartLoader',$_REQUEST)) {
		$infinitely = '';
		if(array_key_exists('infinitely',$_REQUEST)) $infinitely = '--infinitely';
		exec("$phpCLIexec loaderSched.php $infinitely > /dev/null 2>&1 &",$ret,$status);
		//echo "exec ret="; print_r($ret); echo "status=$status;\n";
		sleep(1);
	}
	
	$stopLoader = false;
	if(array_key_exists('stopLoader',$_REQUEST)) $stopLoader = true;

	$jobsInfo = array();
	clearstatcache();
	foreach(preg_grep('~.[0-9]$~', scandir($jobsDir)) as $jobName) {	 	// возьмём только файлы с цифровым расшрением
		$jobSize = filesize("$jobsDir/$jobName");
		if(!$jobSize) continue;	// внезапно может оказаться файл нулевой длины
		$jobComleteSize =  @filesize("$jobsInWorkDir/$jobName"); 	// файла в этот момент может уже и не оказаться
		//echo "jobSize=$jobSize; jobComleteSize=$jobComleteSize; <br>\n";
		if($jobComleteSize==0) $jobComleteSize = $jobSize;
		$jobsInfo[$jobName] = round((1-$jobComleteSize/$jobSize)*100); 	// выполнено
		
		if($stopLoader) {	// просто удалим выполняющиеся файлы заданий
			unlink($jobName);
			unlink("$jobsDir/$jobName");
		};
	};
	//echo "jobsInfo:<pre>"; print_r($jobsInfo); echo "</pre>";
	// Определим, запущен ли загрузчик
	$schedInfo = glob("$jobsDir/*.slock"); 	// имеющиеся PIDs запущенных планировщиков. Должен быть только один, но мало ли...
	//echo "schedInfo:<pre>"; print_r($schedInfo); echo "</pre>";
	$schedPID = FALSE;
	foreach($schedInfo as $schedPID) {
		$schedPID=explode('.slock',end(explode('/',$schedPID)))[0]; 	// basename не работает с неанглийскими буквами!!!!
		if(file_exists( "/proc/$schedPID")) break; 	// процесс с таким PID работает
		else {
			unlink("$jobsDir/$schedPID.slock"); 	// файл-флаг остался от чего-то, но процесс с таким PID не работает - удалим
			$schedPID = FALSE;
		}
	}
	//echo "schedPID=$schedPID; <br>\n";
	$result = array("loaderRun"=>$schedPID,"jobsInfo"=>$jobsInfo);
}
else {
	$result = array("usage"=>$usage);
};

ob_clean(); 	// очистим, если что попало в буфер
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header('Content-Type: application/json;charset=utf-8;');

//echo json_encode($result,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo json_encode($result,JSON_UNESCAPED_UNICODE);	// однострочный JSON передастся быстрее

$content_lenght = ob_get_length();
header("Content-Length: $content_lenght");
ob_end_flush(); 	// отправляем и прекращаем буферизацию
?>
