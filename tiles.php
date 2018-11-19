<?php session_start(); 	// используется для хранения токена NAVIONICS
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* Original source http://wiki.openstreetmap.org/wiki/ProxySimplePHP
*	Modified to use directory structure matching the OSM urls and retries on a failure
*/
$now = microtime();
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта
require_once('fcommon.php');

require('params.php'); 	// пути и параметры

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
// определимся с источником карты
require_once("$mapSourcesDir/$r.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
// возьмём тайл
$fileName = "$tileCacheDir/$r/$z/$x/$y.$ext"; 	// из кэша
//echo "file=$fileName; <br>\n";
//return;
$img = null; $tries = 1;
$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
if( ! $ttl) $ttl = time(); 	// ttl == 0 - тайлы никогда не протухают
//clearstatcache(); 	// вопросы к файловой системе кешируются глобально или локально?
//$fileNamePresent = @is_file($fileName); 	// тайл есть?
$tile = @file_get_contents($fileName); 	// попробуем взять тайл из кеша
if ($functionGetURL AND ((!$tile) OR ((time()-filemtime($fileName)-$ttl) > 0))) { 	// если есть функция получения тайла, и нет в кэше или файл протух
	//error_log("No $r/$z/$x/$y tile exist?:".!$tile."; Expired to ".(time()-filemtime($fileName)-$ttl)."sec.");
	if($tile AND !$forceFresh) 	{ 	// тайл есть, и не указано, что нужно обязательно свежий
		// отдадим существующий
		$jobName = "$r.$z"; 	// имя файла задания
		file_put_contents("$jobsInWorkDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для загрузчика
		file_put_contents("$jobsDir/$jobName", "$x,$y\n",FILE_APPEND); 	// создадим/добавим файл задания для планировщика
		if(!glob("$jobsDir/*.slock")) { 	// если не запущено ни одного загрузчика
			//error_log("Need scheduler!");
			exec("$phpCLIexec loaderSched.php > /dev/null 2>&1 &"); 	// если запускать сам файл, ему нужны права
		}
	}
	else { 	// тайл надо получать
		eval($functionGetURL); 	// создадим функцию GetURL
		do {
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
			$img = NULL;
			$uri = getURL($z,$x,$y); 	// получим url и массив с контекстом: заголовками, etc.
			//echo "Источник:<pre>"; print_r($uri); echo "</pre>";
			if(is_array($uri))	list($uri,$opts) = $uri;
			if(!$uri) break; 	// по каким-то причинам (например, нет токена для Navionics) не удалось получить uri тайла
			if(!$opts['ssl']) { 	// откажемся от проверок ssl сертификатов, потому что сертификатов у нас нет
				$opts['ssl']=array(
				"verify_peer"=>FALSE,
				"verify_peer_name"=>FALSE
	 		   );
			}
			if(!$opts['http']['proxy'] AND $globalProxy) { 	// глобальный прокси
				$opts['http']=array(
				'proxy'=>$globalProxy,
				'request_fulluri'=>TRUE
				);
			}
			if(!$opts['http']['timeout']) { 
				if($runCLI) $opts['http']['timeout'] = (float)(5*$getTimeout);
				else 	$opts['http']['timeout'] = (float)$getTimeout;	// таймаут ожидания получения тайла, сек
			}
			//echo "opts :<pre>"; print_r($opts); echo "</pre>";
			$context = stream_context_create($opts); 	// таким образом, $opts всегда есть
			$img = @file_get_contents($uri, FALSE, $context); 	// бессмыслено проверять проблемы - с ними всё равно ничего нельзя сделать
			//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";
			//print_r($img);
			if(!$http_response_header) { 	 //echo "связи нет<br>\n";
				$_SESSION['noInternetTimeStart'] = time(); 	// 
				$img = NULL;
				if($runCLI) { 	// если спрашивали из загрузчика - будем вечно стоять в ожидании связи
					sleep($tryTimeout);
					continue;
				}
				else break; 	 // если спрашивали из браузера - не будем спрашивать тайл, и поедем дальше
			}
			$mime_type = finfo_buffer($file_info,$img);
			//echo "mime_type=$mime_type<br>\n";		//print_r($img);
			if (substr($mime_type,0,5)=='image') {
				if($globalTrash) { 	// имеется глобальный список ненужных тайлов
					if($trash) $trash = array_merge($trash,$globalTrash);
					else $trash = $globalTrash;
				}
				if($trash) { 	// имеется список ненужных тайлов
					$imgHash = hash('crc32b',$img);
					//echo "imgHash=$imgHash;<br>\n";
					if(in_array($imgHash,$trash)) { 	// принятый тайл - мусор
						$img = null;
						break;
					}
				}
				// теперь тайл получен
				$tile = $img; unset($img);
				$umask = umask(0); 	// сменим и запомним
				//@mkdir(dirname($fileName), 0755, true);
				@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
				umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
				//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
				//echo "Кешируем $fileName<br>\n";
				
				$fp = fopen($fileName, "w");
				fwrite($fp, $tile);
				fclose($fp);
				chmod($fileName,0777); 	// чтобы при запуске от другого юзера была возаможность заменить тайл, когда он протухнет
				
				//echo "Тайл получен с $tries попытки <br>\n";
				break; 	// тайл получили
			}
			elseif (substr($mime_type,0,5)=='text') { 	// файла нет или не дадут. Но OpenTopo потом даёт
				$img = null;
				//break;
			}
			// Тайла не получили, надо подождать
			//echo "Попытка № $tries - тайла не получено <br>\n";
			$tries++;
			if ($tries > $maxTry) {	// Ждать больше нельзя
				$img = null; 	// Тайла не получили
				break;
			}
			sleep($tryTimeout);
		} while (TRUE); 	// Будем пробовать получить, пока не получим
	}
} 
//print_r($img);
if($runCLI) return; 	// не будем отдавать картинку в cli
//return;
// отдадим тайл
if($tile) { 	// тайла могло не быть в кеше, и его не удалось получить
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
$now=microtime()-$now;
//error_log("Tile $r/$z/$x/$y get for $now sec");
?>
