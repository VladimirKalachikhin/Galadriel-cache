<?php session_start();
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* Original source http://wiki.openstreetmap.org/wiki/ProxySimplePHP
*	Modified to use directory structure matching the OSM urls and retries on a failure
*/
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
if( ! $ttl) $ttl = time(); 	// ttl == 0 - тайлы никогда не протухают
//clearstatcache(); 	// вопросы к файловой системе кешируются глобально или локально?
$fileNamePresent = @is_file($fileName); 	// тайл есть?
if ($functionGetURL AND ((!$fileNamePresent) OR (@filemtime($fileName) < (time()-$ttl)))) { 	// если есть функция получения тайла, и нет в кэше или файл протух
//if (function_exists('GetURL') AND ((!$fileNamePresent) OR (@filemtime($fileName) < (time()-$ttl)))) { 	// если есть функция получения тайла, и нет в кэше или файл протух // В loaderSched файлы источников загружаются по очереди, поэтому нельзя require_once
	eval($functionGetURL); 	// создадим функцию GetURL
	$file_info = finfo_open(FILEINFO_MIME_TYPE);
	do {
		$uri = getURL($z,$x,$y); 	// получим url и массив с контекстом: заголовками, etc.
		echo "Источник:<pre>"; print_r($uri); echo "</pre>";
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
			$opts['http']['timeout'] = $getTimeout;	// таймаут ожидания получения тайла, сек
		}
		//echo "opts :<pre>"; print_r($opts); echo "</pre>";
		$context = stream_context_create($opts); 	// таким образом, $opts всегда есть
		$img = @file_get_contents($uri, FALSE, $context); 	// бессмыслено проверять проблемы - с ними всё равно ничего нельзя сделать
		//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";
		$mime_type = finfo_buffer($file_info,$img);
		//echo "mime_type=$mime_type<br>\n";		//print_r($img);
		if (substr($mime_type,0,5)=='image') {
			if($trash) { 	// имеется список ненужных тайлов
				$imgHash = hash('crc32b',$img);
				//echo "imgHash=$imgHash;<br>\n";
				if(in_array($imgHash,$trash)) { 	// принятый тайл - мусор
					$img = null;
					break;
				}
			}
			$umask = umask(0); 	// сменим и запомним
			//@mkdir(dirname($fileName), 0755, true);
			@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
			//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
			//echo "Кешируем $fileName<br>\n";
			$fp = fopen($fileName, "w");
			fwrite($fp, $img);
			fclose($fp);
			chmod($fileName,0777); 	// чтобы запуск от другого юзера
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
	if($fileNamePresent AND (! $img)) {
		$img = file_get_contents($fileName); 	// если получить не удалось, но был старый файл
		$mime_type = finfo_buffer($file_info,$img);
	}
} 
else {
	$img = file_get_contents($fileName);
}
//print_r($img);
if($runCLI) return; 	// не будем отдавать картинку в cli
//return;
// отдадим тайл
if($img) {
	if ($fileNamePresent) {
		$exp_gmt = gmdate("D, d M Y H:i:s", time() + 60*60) ." GMT"; 	// Тайл будет стопудово кешироваться браузером 1 час
		$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($fileName)) ." GMT";
		header("Expired: " . $exp_gmt);
		header("Last-Modified: " . $mod_gmt);
	}
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
echo $img;
$content_lenght = ob_get_length();
header("Content-Length: $content_lenght");
ob_end_flush(); 	// отправляем тело - собственно картинку и прекращаем буферизацию
?>
