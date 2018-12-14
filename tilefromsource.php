<?php session_start();
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* 
*	Get tile from souce

usually as nginx error 404 handler
/tiles/OpenTopoMap/11/1185/578.png
*/
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
$selfPath = $path_parts['dirname'];
chdir($selfPath); // задаем директорию выполнение скрипта
require_once('fcommon.php');

require('params.php'); 	// пути и параметры
if($mapSourcesDir[0]!='/') $mapSourcesDir = "$selfPath/$mapSourcesDir";	// если путь относительный
if($tileCacheDir[0]!='/') $tileCacheDir = "$selfPath/$tileCacheDir";	// если путь относительный

$uri = $_REQUEST['uri']; 	// запрос, переданный от nginx. Считаем, что это запрос тайла
//echo "Исходный uri=$uri; <br>\n";
// Разберём этот uri
$path_parts = pathinfo($uri); // 
if(!$path_parts['extension']) {$img = null; goto out;} 	// что-то не то с путями, но обломаться не должно, поэтому покажем пусто
$y = $path_parts['filename'];
$pos = strrpos($path_parts['dirname'],'/');
$x = substr($path_parts['dirname'],$pos+1); 	// строка после слеша - x
$path_parts['dirname'] = substr($path_parts['dirname'],0,$pos); 	// отрежем x
$pos = strrpos($path_parts['dirname'],'/');
$z = substr($path_parts['dirname'],$pos+1); 	// строка после слеша - z
$path_parts['dirname'] = substr($path_parts['dirname'],0,$pos); 	// отрежем z
$pos = strrpos($path_parts['dirname'],'/');
$mapSourcesFile = substr($path_parts['dirname'],$pos+1); 	// строка после слеша - наименование карты
$path_parts['dirname'] = substr($path_parts['dirname'],0,$pos); 	// отрежем наименование карты
//echo "mapSourcesFile=$mapSourcesFile; z=$z; x=$x; y=$y; <br>\n";
//echo "path_parts['dirname']=".$path_parts['dirname']."<br>mapSourcesDir=$mapSourcesDir;<br>tileCacheDir=$tileCacheDir;<br>\n";
// определимся с источником карты
require_once("$mapSourcesDir/$mapSourcesFile.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда. Может случиться, что с именем что-то не так - подавим ошибку
$fileName = "$tileCacheDir/$mapSourcesFile/$z/$x/$y.$ext"; 	// 
//echo "fileName=$fileName;<br>\n";
//return;
$tries = 0; $img = FALSE;
if ($functionGetURL) { 	// если есть функция получения тайла
//if (function_exists('GetURL')) { 	// если есть функция получения тайла 	// В loaderSched файлы источников загружаются по очереди, поэтому нельзя require_once
	eval($functionGetURL); 	// создадим функцию GetURL
	$file_info = finfo_open(FILEINFO_MIME_TYPE);
	do {
		if($_SESSION['noInternetTimeStart']) { 	// ранее было обнаружено отсутствие интернета
			if((time()-$_SESSION['noInternetTimeStart']-$noInternetTimeout)<0) {	// если таймаут из конфига не истёк
				//echo "связи нет, пропускаем ".(time()-$_SESSION['noInternetTimeStart']-$noInternetTimeout)." секунд <br>\n";
				break; 	 // не будем спрашивать тайл, и поедем дальше
			}
		}
		$img = FALSE; 
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
			$opts['http']['proxy']=$globalProxy;
			$opts['http']['request_fulluri']=TRUE;
		}
		if(!$opts['http']['timeout']) { 
			$opts['http']['timeout'] = $getTimeout;	// таймаут ожидания получения тайла, сек
		}
		$context = stream_context_create($opts); 	// таким образом, $opts всегда есть
		//echo "url=$url;<br>\n";
		$img = file_get_contents($uri, FALSE, $context); 	// бессмыслено проверять проблемы - с ними всё равно ничего нельзя сделать
		//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";
		// Обработка проблем ответа
		if(!$http_response_header) { 	 //echo "связи нет<br>\n";
			$_SESSION['noInternetTimeStart'] = time(); 	// 
			$img = FALSE;
			break; 	 //  поедем дальше
		}
		elseif(strpos($http_response_header[0],'404') !== FALSE) { 	// файл не найден. Следует ли сохранять в кеше что-то типа .tne ?
			$img = NULL; 	// картинки нет, потому что её нет
		}
		// Обработка проблем полученного
		$mime_type = finfo_buffer($file_info,$img);
		//echo "mime_type=$mime_type<br>\n";		print_r($img);
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
		elseif (substr($mime_type,0,4)=='text') { 	// файла нет или не дадут. Но OpenTopo потом даёт
			error_log($img);
			$img = FALSE;
		}
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
			
			$umask = umask(0); 	// сменим и запомним
			@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё.
			//echo "Кешируем $fileName<br>\n";
			$fp = fopen($fileName, "w");
			fwrite($fp, $img);
			fclose($fp);
			chmod($fileName,0777); 	// чтобы запуск от другого юзера
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
			
			break; 	// тайл получили
		}
		// Тайла не получили, надо подождать
		//echo "Попытка № $tries - тайла не получено\n";
		$tries++;
		if ($tries > $maxTry) {	// Ждать больше нельзя
			$img = FALSE; 	// Тайла не получили
			break;
		}
		sleep($tryTimeout);
	} while (TRUE); 	// 
} 
//echo "Изображение:<pre>"; print_r($img); echo "</pre>";
//echo "Конец изображения<br>\n";
//return;
// отдадим тайл
out:
if($img) {
	if (@is_file($fileName)) {
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
