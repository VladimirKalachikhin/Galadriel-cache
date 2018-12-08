<?php session_start(); 	// используется для хранения токена NAVIONICS
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* Original source http://wiki.openstreetmap.org/wiki/ProxySimplePHP
*	Modified to use directory structure matching the OSM urls and retries on a failure
Берёт тай из кеша и сразу отдаёт-показывает.
Потом, если надо - скачивает
Если получено 404 - сохраняет пустой тайл, в остальных случаях - переспрашивает.
Если полученный тайл ещё не показывали (он новый) - показываем.
В CLI тайл не показываем, только скачиваем.
*/
//$now = microtime(TRUE);
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта
require_once('fcommon.php');

require('params.php'); 	// пути и параметры
$bannedSources = array();
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
if($runCLI) $maxTry = 2 * $maxTry;
// определимся с источником карты
require_once("$mapSourcesDir/$r.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
// возьмём тайл
$fileName = "$tileCacheDir/$r/$z/$x/$y.$ext"; 	// из кэша
//echo "file=$fileName; <br>\n";
//return;
$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша, возможно, за приделами разрешённых масштабов
if((!$runCLI) AND $img) 	{ 	// тайл есть
	showTile($img,$ext); 	// сначала покажем
	$tileShowed = TRUE;
	$from = 1;
}
// потом получим
if( ! $ttl) $ttl = time(); 	// ttl == 0 - тайлы никогда не протухают
if ((($z <= $maxZoom) AND $z >= $minZoom) AND $functionGetURL AND ((!$img) OR ((time()-filemtime($fileName)-$ttl) > 0))) { 	// если масштаб допустим, есть функция получения тайла, и нет в кэше или файл протух
	//error_log("No $r/$z/$x/$y tile exist?:".!$img."; Expired to ".(time()-filemtime($fileName)-$ttl)."sec. maxZoom=$maxZoom;");
	// тайл надо получать
	$img = FALSE; $tries = 1; 
	eval($functionGetURL); 	// создадим функцию GetURL
	do {
		// Проблема со связью. В cli запуск загрузки для проблемного источника не допустит loader
		if($_SESSION['noInternetTimeStart']) { 	// ранее было обнаружено отсутствие интернета
			if((time()-$_SESSION['noInternetTimeStart']-$noInternetTimeout)<0) {	// если таймаут из конфига не истёк
				//echo "связи нет, ждём/пропускаем ".(time()-$_SESSION['noInternetTimeStart']-$noInternetTimeout)." секунд <br>\n";
				if($runCLI) { 	// если спрашивали из загрузчика - будем вечно стоять в ожидании связи
					sleep($tryTimeout);
					continue;
				}
				else break; 	 // если спрашивали из браузера - не будем спрашивать тайл, и поедем дальше
			}
		}
		$uri = getURL($z,$x,$y); 	// получим url и массив с контекстом: заголовками, etc.
		//echo "Источник:<pre>"; print_r($uri); echo "</pre>";
		if(is_array($uri))	list($uri,$opts) = $uri;
		if(!$uri) break; 	// по каким-то причинам (например, нет токена для Navionics) не удалось получить uri тайла

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
		$img = file_get_contents($uri, FALSE, $context); 	// бессмыслено проверять проблемы - с ними всё равно ничего нельзя сделать
		//error_log($uri);
		//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";
		//error_log( print_r($http_response_header,TRUE));
		//print_r($img);
		// Обработка проблем ответа
		if(!$http_response_header) { 	 //echo "связи нет<br>\n";
			$_SESSION['noInternetTimeStart'] = time(); 	// 
			$img = FALSE; 	// картинки нет по непонятной прчине
			if($runCLI) { 	// если спрашивали из загрузчика - будем  стоять в ожидании связи
				$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName)); 	// считаем файл проблем
				if(!$bannedSources) $bannedSources = array();
				$bannedSources[$r] = TRUE; 	// отметим проблемы с источником
				$umask = umask(0); 	// сменим на 0777 и запомним текущую
				file_put_contents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
				@chmod($bannedSourcesFileName,0777); 	// чтобы при запуске от другого юзера была возаможность 
				umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
				error_log("tiles: $r banned!");
				//echo "Попытка № $tries - тайла не получено из-за отсутствия связи или умирания источника<br>\n";
				/* если не ждать вечно - тайлы будут пропускаться загрузчиком, и об этом никто не узнает.
				$tries++;
				if ($tries > $maxTry) {	// Ждать больше нельзя
					$img = null; 	// Тайла не получили
					break; 	
				}
				*/
				sleep($tryTimeout);
				continue;
			}
			else break; 	 // если спрашивали из браузера - не будем спрашивать тайл, и поедем дальше
		}
		elseif(strpos($http_response_header[0],'404') !== FALSE) { 	// файл не найден. Следует ли сохранять в кеше что-то типа .tne ?
			$img = NULL; 	// картинки нет, потому что её нет
		}
		// Обработка проблем полученного
		$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
		$mime_type = finfo_buffer($file_info,$img);
		//echo "mime_type=$mime_type<br>\n";		//print_r($img);
		//error_log("mime_type=$mime_type");		//print_r($img);
		if (substr($mime_type,0,5)=='image') {
			if($globalTrash) { 	// имеется глобальный список ненужных тайлов
				if($trash) $trash = array_merge($trash,$globalTrash);
				else $trash = $globalTrash;
			}
			if($trash) { 	// имеется список ненужных тайлов
				$imgHash = hash('crc32b',$img);
				//echo "imgHash=$imgHash;<br>\n";
				if(in_array($imgHash,$trash)) { 	// принятый тайл - мусор
					$img = FALSE;
					break;
				}
			}
		}
		//elseif (substr($mime_type,0,5)=='text') { 	// файла нет или не дадут. Но OpenTopo потом даёт
		else { 	// файла нет или не дадут. Но OpenTopo потом даёт
			$img = FALSE;
			if(strpos($http_response_header[0],'301') !== FALSE) { 	// куда-то перенаправляли, по умолчанию в $opts - следовать
				//error_log( print_r($http_response_header,TRUE));
				foreach($http_response_header as $header) {
					if(strpos($header,'404') !== FALSE) { 	// файл не найден.
						$img = NULL;
						break; 	// прекратим спрашивать, если перенаправили на 404
					}
				}
			}
		}
		if($img !== FALSE) {	// теперь тайл получен, возможно, пустой в случае 404
			$umask = umask(0); 	// сменим на 0777 и запомним текущую
			//@mkdir(dirname($fileName), 0755, true);
			@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
			//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
			//echo "Кешируем $fileName<br>\n";
			
			$fp = fopen($fileName, "w");
			fwrite($fp, $img);
			fclose($fp);
			@chmod($fileName,0777); 	// чтобы при запуске от другого юзера была возаможность заменить тайл, когда он протухнет
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
			
			//echo "Тайл получен с $tries попытки <br>\n";
			break; 	// тайл получили
		}
		// Тайла не получили, надо подождать
		//echo "Попытка № $tries - тайла не получено <br>\n";
		$tries++;
		if ($tries > $maxTry) {	// Ждать больше нельзя
			$img = NULL; 	// Тайла не получили
			break;
		}
		sleep($tryTimeout);
	} while (TRUE); 	// Будем пробовать получить, пока не получим
	
	// отдадим тайл
	if((!$runCLI) AND (!$tileShowed)) showTile($img,$ext); 	//покажем тайл, если ещё не показывали.
	
	if(($img !== FALSE) AND $runCLI AND $bannedSources[$r]) { 	// снимем проблемы с источником, получили мы тайл или нет
		$bannedSources = unserialize(file_get_contents($bannedSourcesFileName));
		$bannedSources[$r] = FALSE; 	// снимем проблемы с источником
		file_put_contents($bannedSourcesFileName, serialize($bannedSources));
		error_log("tiles: $r unbanned!");
	}
	// Опережающее скачивание при показе - длжно помочь с крупными масштабами
	if((!$runCLI) AND ($z>13) AND ($z<$loaderMaxZoom)) { 	// поставим задание на получение всех нижележащих тайлов
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
	$from = 2;
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
error_log("Tile $r/$z/$x/$y get $from for $now sec");
*/
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
?>
