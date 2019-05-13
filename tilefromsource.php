<?php session_start();
ob_start(); 	// попробуем перехватить любой вывод скрипта
/* 
	Get tile from souce

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
if(!$path_parts['extension']) {$img = null; goto END;} 	// что-то не то с путями, но обломаться не должно, поэтому покажем пусто
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
$newimg = FALSE; 	// 
if ($functionGetURL) { 	// если есть функция получения тайла
//if (function_exists('GetURL')) { 	// если есть функция получения тайла 	// В loaderSched файлы источников загружаются по очереди, поэтому нельзя require_once
	// определимся с наличием проблем связи и источника карты
	$bannedSources = $_SESSION['bannedSources'];
	if((time()-$bannedSources[$r]-$noInternetTimeout)<0) {	// если таймаут из конфига не истёк
		goto END;
	}
	// Проблем связи и источника нет - будем получать тайл
	eval($functionGetURL); 	// создадим функцию GetURL
	$tries = 1;
	$file_info = finfo_open(FILEINFO_MIME_TYPE);
	do {
		$newimg = FALSE; 	// умолчально - тайл получить не удалось, ничего не сохраняем, пропускаем
		$uri = getURL($z,$x,$y); 	// получим url и массив с контекстом: заголовками, etc.
		if(!$uri) { 	// по каким-то причинам нет uri тайла
			$newimg = NULL; 	// очевидно, картинки нет и не будет
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
			$opts['http']['proxy']=$globalProxy;
			$opts['http']['request_fulluri']=TRUE;
		}
		if(!$opts['http']['timeout']) { 
			if($runCLI) $opts['http']['timeout'] = (float)(5*$getTimeout);
			else 	$opts['http']['timeout'] = (float)$getTimeout;	// таймаут ожидания получения тайла, сек
		}
		$context = stream_context_create($opts); 	// таким образом, $opts всегда есть

		// Запрос - собственно, получаем файл
		$newimg = @file_get_contents($uri, FALSE, $context); 	// 

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
			foreach($http_response_header as $header) {
				if((substr($header,0,4)=='HTTP') AND (strpos($header,'200') !== FALSE)) break; 	// файл получен, перейдём к обработке
				elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'404') !== FALSE)) { 	// файл не найден.
					$newimg = NULL;
					break 2; 	// бессмысленно ждать, уходим
				}
				elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'403') !== FALSE)) { 	// Forbidden.
					if($on403=='skip') $newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
					else 	doBann($r); 	// забаним источник 
					break 2; 	 // бессмысленно ждать, уходим
				}
				elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'503') !== FALSE)) { 	// Service Unavailable
					if ($tries > $maxTry-1) { 	// ждём
						doBann($r); 	// напоследок забаним источник
						break 2; 	 	// уходим
					}
				}
			}
		}
		// Обработка проблем полученного
		$mime_type = finfo_buffer($file_info,$newimg);
		if (substr($mime_type,0,5)=='image') {
			if($globalTrash) { 	// имеется глобальный список ненужных тайлов
				if($trash) $trash = array_merge($trash,$globalTrash);
				else $trash = $globalTrash;
			}
			if($trash) { 	// имеется список ненужных тайлов
				$imgHash = hash('crc32b',$newimg);
				if(in_array($imgHash,$trash,TRUE)) { 	// принятый тайл - мусор, TRUE - для сравнения без преобразования типов
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
		$tries++;
		if ($tries > $maxTry) {	// Ждать больше нельзя
			//$newimg = NULL; 	// Тайла не получили - считаем, что тайла нет, сохраним пустой
			//doBann($r); 	// забаним источник
			break;
		}
		sleep($tryTimeout);
	} while (TRUE); 	// 
} 
END:
// покажем тайл
showTile($newimg,$ext); 	//покажем тайл. Если $newimg===FALSE, будет показано 404
// сохраним тайл
if($newimg !== FALSE) {	// теперь тайл получен, возможно, пустой в случае 404 или мусорного тайла
		
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	//@mkdir(dirname($fileName), 0755, true);
	@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
	//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
	$fp = fopen($fileName, "w");
	fwrite($fp, $newimg);
	fclose($fp);
	@chmod($fileName,0777); 	// чтобы при запуске от другого юзера была возаможность заменить тайл, когда он протухнет
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		
}
if(($newimg !== FALSE) AND $bannedSources[$r]) { 	// снимем проблемы с источником, получили мы тайл или нет
	$bannedSources[$r] = FALSE; 	// снимем проблемы с источником
	$_SESSION['bannedSources'] = $bannedSources;
	error_log("tiles.php: Попытка № $tries: $r unbanned!");
}

// Опережающее скачивание при показе - должно помочь с крупными масштабами
if(($z>13) AND ($z<$loaderMaxZoom) AND $newimg) { 	// поставим задание на получение всех нижележащих тайлов, если этот тайл удачно скачался
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

?>
