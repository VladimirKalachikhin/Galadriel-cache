<?php session_start(); 	// используется для хранения токена NAVIONICS
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* Original source http://wiki.openstreetmap.org/wiki/ProxySimplePHP
*	Modified to use directory structure matching the OSM urls and retries on a failure
Берёт тай из кеша и сразу отдаёт-показывает.
Потом, если надо - скачивает
Если получено 404 - сохраняет пустой тайл, в остальных случаях - переспрашивает.
Ксли принятый файл - в списке мусорных,сохраняем пустой
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
$bannedSourcesFileName = "$jobsDir/bannedSources";

if($argv) { 	// cli
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
	$x = intval($_REQUEST['x']);
	$y = intval($_REQUEST['y']);
	$z = intval($_REQUEST['z']);
	$r = strip_tags($_REQUEST['r']);
}
if(!$x) $x=0;
if(!$y) $y=0;
if(!$z) $z=0;
if(!$r) $r = 'osmmapMapnik';
if($runCLI) $maxTry = 3 * $maxTry; 	// увеличим число попыток скачать файл, если запущены загрузчиком
// определимся с источником карты
require_once("$mapSourcesDir/$r.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
// возьмём тайл
$fileName = "$tileCacheDir/$r/$z/$x/$y.$ext"; 	// из кэша
//echo "file=$fileName; <br>\n";
//return;
$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша, возможно, за приделами разрешённых масштабов
//error_log("Получено ".strlen($img)." bytes из кэша");		
if((!$runCLI) AND ($img!==FALSE)) 	{ 	// тайл есть, возможно, пустой, спросили из браузера
	showTile($img,$ext); 	// сначала покажем
	//$from = 1;
}
// потом получим
if( ! $ttl) $ttl = time(); 	// ttl == 0 - тайлы никогда не протухают
$newimg = FALSE; 	// 
if ((($z <= $maxZoom) AND $z >= $minZoom) AND $functionGetURL AND (($img===FALSE) OR ((time()-filemtime($fileName)-$ttl) > 0))) { 	// если масштаб допустим, есть функция получения тайла, и нет в кэше или файл протух
	//error_log("No $r/$z/$x/$y tile exist?:".!$img."; Expired to ".(time()-filemtime($fileName)-$ttl)."sec. maxZoom=$maxZoom;");
	// тайл надо получать
	// определимся с наличием проблем связи и источника карты
	if($runCLI)	$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName)); 	// считаем файл проблем
	else 		$bannedSources = $_SESSION['bannedSources'];
	if((time()-$bannedSources[$r]-$noInternetTimeout)<0) {	// если таймаут из конфига не истёк
		if(!$runCLI) showTile(NULL,NULL); 	// покажем 404 
		//error_log("Source are banned!\n");
		goto END;
	}
	// Проблем связи и источника нет - будем получать тайл
	$tries = 1; 
	eval($functionGetURL); 	// создадим функцию GetURL
	do {
		$newimg = FALSE; 	// умолчально - тайл получить не удалось, ничего не сохраняем, пропускаем
		$uri = getURL($z,$x,$y); 	// получим url и массив с контекстом: заголовками, etc.
		//echo "Источник:<pre>"; print_r($uri); echo "</pre>";
		if(!$uri) { 	// по каким-то причинам нет uri тайла
			$newimg = NULL; 	// очевидно, картинки картинки нет и не будет
			break;
		}
		// Параметры запроса
		if(is_array($uri))	list($uri,$opts) = $uri;
		if(!$opts['http']) {
			$opts['http']=array(
				'method'=>"GET"
			);
		}
		if(!$opts['ssl']) { 	// откажемся от проверок ssl сертификатов, потому что сертификатов у нас нет
			$opts['ssl']=array(
			"verify_peer"=>FALSE,
			"verify_peer_name"=>FALSE
 		   );
		}
		if(!$opts['http']['proxy'] AND $globalProxy) { 	// глобальный прокси
			//error_log("Set global proxy $globalProxy");
			$opts['http']['proxy']=$globalProxy;
			$opts['http']['request_fulluri']=TRUE;
		}
		if(!$opts['http']['timeout']) { 
			if($runCLI) $opts['http']['timeout'] = (float)(5*$getTimeout);
			else 	$opts['http']['timeout'] = (float)$getTimeout;	// таймаут ожидания получения тайла, сек
		}
		//echo "opts :<pre>"; print_r($opts); echo "</pre>";
		//error_log("opts :" . print_r($opts,TRUE));
		$context = stream_context_create($opts); 	// таким образом, $opts всегда есть

		// Запрос - собственно, получаем файл
		$newimg = @file_get_contents($uri, FALSE, $context); 	// 

		//error_log($uri);
		//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";
		//error_log( print_r($http_response_header,TRUE));
		//print_r($newimg);
		// Обработка проблем ответа
		if((!$http_response_header)) { 	 //echo "связи нет  ".$http_response_header[0]."<br>\n";
			doBann($r); 	// забаним источник
			break; 	 // бессмысленно ждать, уходим
		}
		elseif(strpos($http_response_header[0],'403') !== FALSE) { 	// Forbidden
			if($on403=='skip') $newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
			else 	doBann($r); 	// забаним источник 
			break; 	 // бессмысленно ждать, уходим
		}
		elseif(strpos($http_response_header[0],'404') !== FALSE) { 	// файл не найден.
			$newimg = NULL; 	// картинки нет, потому что её нет
			break; 	 // бессмысленно ждать, уходим
		}
		elseif(strpos($http_response_header[0],'301') !== FALSE) { 	// куда-то перенаправляли, по умолчанию в $opts - следовать
			//error_log( print_r($http_response_header,TRUE));
			foreach($http_response_header as $header) {
				if(strpos($header,'200') !== FALSE) break; 	// файл получен, перейдём к обработке
				elseif(strpos($header,'404') !== FALSE) { 	// файл не найден.
					$newimg = NULL;
					break 2; 	// бессмысленно ждать, уходим
				}
				elseif(strpos($header,'403') !== FALSE) { 	// Forbidden.
					if($on403=='skip') $newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
					else 	doBann($r); 	// забаним источник 
					break 2; 	 // бессмысленно ждать, уходим
				}
				elseif(strpos($header,'503') !== FALSE) { 	// Service Unavailable
					if ($tries > $maxTry-1) { 	// ждём
						doBann($r); 	// напоследок забаним источник
						break 2; 	 	// уходим
					}
				}
			}
		}
		// Обработка проблем полученного
		$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
		$mime_type = finfo_buffer($file_info,$newimg);
		//error_log("mime_type=$mime_type");
		if (substr($mime_type,0,5)=='image') {
			if($globalTrash) { 	// имеется глобальный список ненужных тайлов
				if($trash) $trash = array_merge($trash,$globalTrash);
				else $trash = $globalTrash;
			}
			if($trash) { 	// имеется список ненужных тайлов
				$imgHash = hash('crc32b',$newimg);
				//echo "imgHash=$imgHash;<br>\n";
				if(in_array($imgHash,$trash)) { 	// принятый тайл - мусор
					$newimg = NULL; 	// тайл принят нормально, но он мусор
					break;
				}
			}
			break; 	// всё нормально, тайл получен
		}
		elseif (substr($mime_type,0,4)=='text') { 	// файла нет или не дадут. Но OpenTopo потом даёт
			error_log($newimg);
		}
		else { 	// файла нет или не дадут.
		}
		// Тайла не получили, надо подождать
		//echo "Попытка № $tries - тайла не получено <br>\n";
		$tries++;
		if ($tries > $maxTry) {	// Ждать больше нельзя
			$newimg = NULL; 	// Тайла не получили - считаем, что тайла нет, сохраним пустой
			//doBann($r); 	// забаним источник
			break;
		}
		sleep($tryTimeout);
	} while (TRUE); 	// Будем пробовать получить, пока не получим
	
	// покажем тайл
	if((!$runCLI) AND ($img===FALSE)) showTile($newimg,$ext); 	//покажем тайл, если ещё не показывали. Если $newimg===FALSE, будет показано 404
	// сохраним тайл
	if($newimg !== FALSE) {	// теперь тайл получен, возможно, пустой в случае 404 или мусорного тайла
		if($newimg OR ($img===FALSE)) { 	// есть свежий тайл или нет старого
			$umask = umask(0); 	// сменим на 0777 и запомним текущую
			//@mkdir(dirname($fileName), 0755, true);
			@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
			//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
			//error_log("Saved ".strlen($newimg)." bytes");		
			$fp = fopen($fileName, "w");
			fwrite($fp, $newimg);
			fclose($fp);
			@chmod($fileName,0777); 	// чтобы при запуске от другого юзера была возаможность заменить тайл, когда он протухнет
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		}		
	}
	if(($newimg !== FALSE) AND $bannedSources[$r]) { 	// снимем проблемы с источником, получили мы тайл или нет
		$bannedSources[$r] = FALSE; 	// снимем проблемы с источником
		if($runCLI)	{ 	// считаем файл проблем
			$umask = umask(0); 	// сменим на 0777 и запомним текущую
			file_put_contents($bannedSourcesFileName, serialize($bannedSources));
			//quickFilePutContents($bannedSourcesFileName, serialize($bannedSources));
			@chmod($bannedSourcesFileName,0777); 	// чтобы при запуске от другого юзера была возаможность 
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		}
		else 		$_SESSION['bannedSources'] = $bannedSources;
		error_log("tiles.php: Попытка № $tries: $r unbanned!");
	}
	
	// Опережающее скачивание при показе - должно помочь с крупными масштабами
	if((!$runCLI) AND ($z>13) AND ($z<$loaderMaxZoom) AND $newimg) { 	// поставим задание на получение всех нижележащих тайлов, если этот тайл удачно скачался
		$jobName = "$r.".($z+1); 	// имя файла задания
		$umask = umask(0); 	// сменим на 0777 и запомним текущую
		file_put_contents("$jobsInWorkDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для загрузчика
		@chmod("$jobsInWorkDir/$jobName",0777); 	// чтобы запуск от другого юзера
		file_put_contents("$jobsDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для планировщика
		@chmod("$jobsDir/$jobName",0777); 	// чтобы запуск от другого юзера
		umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		if(!glob("$jobsDir/*.slock")) { 	// если не запущено ни одного загрузчика
			//error_log("Need scheduler for zoom ".($z+1));
			//exec("$phpCLIexec loaderSched.php > /dev/null 2>&1 &"); 	// если запускать сам файл, ему нужны права
			exec("$phpCLIexec loaderSched.php > /dev/null &"); 	// если запускать сам файл, ему нужны права
		}
	}
	
	//$from = 2;
} 
/*
$now=microtime(TRUE)-$now;
switch($from) {
case 1:
	$f='from cache';
case 2:
	$f.=' from source';
	break;
}
$from = $f; 
error_log("Tile $r/$z/$x/$y get $from for $now sec\n");
*/
END:
if($runCLI) {
	if(($img===FALSE) AND ($newimg === FALSE)) fwrite(STDOUT, '0'); 	// тайла не было и он не был получен
	else fwrite(STDOUT, '1');
}
return;

function showTile($tile,$ext) {
global $runCLI;

if($runCLI) return; 	// не будем отдавать картинку в cli
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
ob_clean(); 	// очистим, если что попало в буфер, но заголовки выше должны отправиться
echo $tile;
$content_lenght = ob_get_length();
header("Content-Length: $content_lenght");
ob_end_flush(); 	// отправляем тело - собственно картинку и прекращаем буферизацию
ob_start(); 	// попробуем перехватить любой вывод скрипта
}

function doBann($r) {
/* Банит источник */
global $bannedSources, $runCLI, $bannedSourcesFileName, $tries, $http_response_header, $_SESSION;
error_log("newimg=$newimg;");
//error_log(print_r($http_response_header,TRUE));

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
error_log("tiles.php: Попытка № $tries: $r banned at ".gmdate("D, d M Y H:i:s", $curr_time)."!");
} // end function doBann
?>
