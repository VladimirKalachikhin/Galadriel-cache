<?php 
/* Функции работы с кешем

getTile($path) - получить файл из источника и положить в кеш
doBann($r,$bannedSourcesFileName) - забанить источник
*/
function getTile($path,$getURLparms=array()) {
/* 
	Get tile from souce

Call to get tile from source, to asinchronous update cache.
Return image or NULL and update cache.

Use: $ php tilefromsource.php '/tiles/OpenTopoMap/11/1185/578.png'

Если получено 404 - сохраняет пустой тайл, в остальных случаях - переспрашивает.
Если принятый файл - в списке мусорных,сохраняем пустой

$getURLparms - массив с параметрами для передаяи функции getURL(), определённой в файлк источника
вообще говоря - произвольный, но будем считать, что туда могут передаваться стандартные переменные 
со своим именем в качестве ключа
*/
require('params.php'); 	// пути и параметры
$bannedSourcesFileName = "$jobsDir/bannedSources";
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
$selfPath = $path_parts['dirname'];
if($mapSourcesDir[0]!='/') $mapSourcesDir = "$selfPath/$mapSourcesDir";	// если путь относительный
if($tileCacheDir[0]!='/') $tileCacheDir = "$selfPath/$tileCacheDir";	// если путь относительный

// Разберём этот path
$path_parts = pathinfo($path); // 
//echo "path_parts<pre>"; print_r($path_parts); echo "</pre><br>\n";
$y = $path_parts['filename'];
$pos = strrpos($path_parts['dirname'],'/');
$x = substr($path_parts['dirname'],$pos+1); 	// строка после слеша - x
$path_parts['dirname'] = substr($path_parts['dirname'],0,$pos); 	// отрежем x
$pos = strrpos($path_parts['dirname'],'/');
$z = substr($path_parts['dirname'],$pos+1); 	// строка после слеша - z
$path_parts['dirname'] = substr($path_parts['dirname'],0,$pos); 	// отрежем z
// теперь $path_parts['dirname'] - имя карты с, возможно, путём к варианту, и с, возможно, предшествующей частью или всем путём к кешу
//echo "tileCacheDir=$tileCacheDir; ".$path_parts['dirname']."<br>\n";
$pos = strrpos($tileCacheDir,'/');
$tileCacheLastDir = substr($tileCacheDir,$pos+1); 	// реальный путь всегда содержит хоть один слеш, даже если он в cli от текущего
$pos = strpos($path_parts['dirname'],$tileCacheLastDir);
if($pos!==FALSE) $path_parts['dirname'] = substr($path_parts['dirname'],$pos+strlen($tileCacheLastDir)+1);
//  теперь $path_parts['dirname'] - имя карты с, возможно, путём к варианту
$pos = strpos($path_parts['dirname'],'/');
if($pos === FALSE) {
	$mapAddPath = '';
	$mapSourcesName = $path_parts['dirname'];
}
else {
	$mapAddPath = substr($path_parts['dirname'],$pos);
	$mapSourcesName = substr($path_parts['dirname'],0,$pos);
}
//echo "mapSourcesName=$mapSourcesName; mapAddPath=$mapAddPath; z=$z; x=$x; y=$y; <br>\n";
//echo "path_parts['dirname']=".$path_parts['dirname']."<br>mapSourcesDir=$mapSourcesDir;<br>tileCacheDir=$tileCacheDir;<br>\n";
// определимся с источником карты
require_once("$mapSourcesDir/$mapSourcesName.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда. Может случиться, что с именем что-то не так - подавим ошибку
if($ext) $fileName = "$tileCacheDir/$mapSourcesName$mapAddPath/$z/$x/$y.$ext"; 	// в конфиге источника указано расширение
elseif($path_parts['extension']) $fileName = "$tileCacheDir/$mapSourcesName$mapAddPath/$z/$x/$y.".$path_parts['extension'];
else $fileName = "$tileCacheDir/$mapSourcesName$mapAddPath/$z/$x/$y.png";
$getURLparms['mapAddPath'] = $mapAddPath;
//echo "fileName=$fileName; <br>\n";
$newimg = FALSE; 	// 
if (!$functionGetURL) goto END;; 	// нет функции для получения тайла	
// есть функция получения тайла
// определимся с наличием проблем связи и источника карты
$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName)); 	// считаем файл проблем
//echo "bannedSources:<pre>"; print_r($bannedSources); echo "</pre>";
if((time()-$bannedSources[$mapSourcesName]-$noInternetTimeout)<0) goto END;;	// если таймаут из конфига не истёк
// Проблем связи и источника нет - будем получать тайл
eval($functionGetURL); 	// создадим функцию GetURL
$tries = 1;
$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
do {
	$newimg = NULL; 	// умолчально - тайл получить не удалось, ничего не сохраняем, пропускаем
	$uri = getURL($z,$x,$y,$getURLparms); 	// получим url и массив с контекстом: заголовками, etc.
	//echo "Источник:<pre>"; print_r($uri); echo "</pre>";
	if(!$uri) goto END;; 	// по каким-то причинам нет uri тайла, очевидно, картинки нет и не будет
	// Параметры запроса
	if(is_array($uri))	list($uri,$opts) = $uri;
	if(!$opts['http']) {
		$opts['http']=array(
			'method'=>"GET"
		);
	}
	if(!@$opts['ssl']) { 	// откажемся от проверок ssl сертификатов, потому что сертификатов у нас нет
		$opts['ssl']=array(
		"verify_peer"=>FALSE,
		"verify_peer_name"=>FALSE
	   );
	}
	if(!@$opts['http']['proxy'] AND @$globalProxy) { 	// глобальный прокси
		$opts['http']['proxy']=$globalProxy;
		$opts['http']['request_fulluri']=TRUE;
	}
	if(!@$opts['http']['timeout']) { 
		$opts['http']['timeout'] = (float)$getTimeout;	// таймаут ожидания получения тайла, сек
	}
	//echo "opts :<pre>"; print_r($opts); echo "</pre>";
	//echo "$uri\n";
	$context = stream_context_create($opts); 	// таким образом, $opts всегда есть

	// Запрос - собственно, получаем файл
	$newimg = @file_get_contents($uri, FALSE, $context); 	// 
	//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>";

	// Обработка проблем ответа
	if((!$http_response_header)) { 	 //echo "связи нет  ".$http_response_header[0]."<br>\n";
		doBann($mapSourcesName,$bannedSourcesFileName); 	// забаним источник
		goto END;; 	 // бессмысленно ждать, уходим совсем
	}
	elseif(strpos($http_response_header[0],'403') !== FALSE) { 	// Forbidden
		if($on403=='skip') $newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
		else 	doBann($mapSourcesName,$bannedSourcesFileName); 	// забаним источник 
		break; 	 // бессмысленно ждать, прекращаем получение тайла
	}
	elseif(strpos($http_response_header[0],'404') !== FALSE) { 	// файл не найден.
		$newimg = NULL; 	// картинки нет, потому что её нет
		break; 	 // бессмысленно ждать, прекращаем получение тайла
	}
	elseif(strpos($http_response_header[0],'301') !== FALSE) { 	// куда-то перенаправляли, по умолчанию в $opts - следовать
		foreach($http_response_header as $header) {
			if((substr($header,0,4)=='HTTP') AND (strpos($header,'200') !== FALSE)) break; 	// файл получен, перейдём к обработке
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'404') !== FALSE)) { 	// файл не найден.
				$newimg = NULL;
				break 2; 	// бессмысленно ждать, прекращаем получение тайла
			}
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'403') !== FALSE)) { 	// Forbidden.
				if($on403=='skip') $newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
				else 	doBann($mapSourcesName,$bannedSourcesFileName); 	// забаним источник 
				break 2; 	 // бессмысленно ждать, прекращаем получение тайла
			}
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'503') !== FALSE)) { 	// Service Unavailable
				if ($tries > $maxTry-1) { 	// ждём
					doBann($mapSourcesName,$bannedSourcesFileName); 	// напоследок забаним источник
					goto END;; 	 // бессмысленно ждать, уходим совсем
				}
			}
		}
	}
	// Обработка проблем полученного
	$mime_type = finfo_buffer($file_info,$newimg);
	if (substr($mime_type,0,5)=='image') {
		if(@$globalTrash) { 	// имеется глобальный список ненужных тайлов
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
		$newimg = FALSE; 	// Тайла не получили
		doBann($mapSourcesName,$bannedSourcesFileName); 	// забаним источник
		//break;
		goto END;; 	 // бессмысленно ждать, уходим совсем
	}
	sleep($tryTimeout);
} while (TRUE); 	// 

// сохраним тайл
if($newimg !== FALSE) {	// теперь тайл получен, возможно, пустой в случае 404 или мусорного тайла
	
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	//@mkdir(dirname($fileName), 0755, true);
	@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
	//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
	$fp = fopen($fileName, "w");
	fwrite($fp, $newimg);
	fclose($fp);
	@chmod($fileName,0777); 	// чтобы при запуске от другого юзера была возможность заменить тайл, когда он протухнет
	error_log("Saved ".strlen($newimg)." bytes to $fileName");	
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		
}

// Обслужим источник
if(($newimg !== FALSE) AND $bannedSources[$mapSourcesName]) { 	// снимем проблемы с источником, получили мы тайл или нет
	$bannedSources[$mapSourcesName] = FALSE; 	// снимем проблемы с источником
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	file_put_contents($bannedSourcesFileName, serialize($bannedSources));
	@chmod($bannedSourcesFileName,0777); 	// чтобы при запуске от другого юзера была возаможность 
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	error_log("fcache.php: Попытка № $tries: $mapSourcesName unbanned!");
}

// Опережающее скачивание при показе - должно помочь с крупными масштабами
if(($z>13) AND ($z<$loaderMaxZoom) AND $newimg) { 	// поставим задание на получение всех нижележащих тайлов, если этот тайл удачно скачался
	$jobName = "$mapSourcesName.".($z+1); 	// имя файла задания
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

END:
return($newimg);
} // end function getTile

function doBann($r,$bannedSourcesFileName) {
/* Банит источник */
//error_log(print_r($http_response_header,TRUE));
//error_log("doBann: bannedSources ".print_r($bannedSources,TRUE));

$curr_time = time();
$bannedSources[$r] = $curr_time; 	// отметим проблемы с источником
//echo "bannedSources:<pre>"; print_r($bannedSources); echo "</pre>";
//echo serialize($bannedSources)."<br>\n";
$umask = umask(0); 	// сменим на 0777 и запомним текущую
file_put_contents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
//quickFilePutContents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
@chmod($bannedSourcesFileName,0777); 	// чтобы при запуске от другого юзера была возаможность 
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
//error_log("doBann: bannedSources ".print_r($bannedSources,TRUE));
error_log("fcache.php doBann: $r banned at ".gmdate("D, d M Y H:i:s", $curr_time)."!");
} // end function doBann
?>

