<?php 
//ob_start(); 	// попробуем перехватить любой вывод скрипта
session_start(); 	// оно не нужно, но в источниках может использоваться, например, в navionics
chdir(__DIR__); // задаем директорию выполнение скрипта
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$params = array();
if(@$argv) { 	// cli
	//print_r($argv);
	$options = getopt("z:x:y:r:",array('maxTry:','tryTimeout:','checkonly'));
	//print_r($options);
	if($options) {
		$x = intval($options['x']);
		$y = filter_var($options['y'],FILTER_SANITIZE_URL); 	// 123456.png
		$z = intval($options['z']);
		$r = filter_var($options['r'],FILTER_SANITIZE_URL);
		$uri = "$r/$z/$x/$y";
		if($options['maxTry']) $params['maxTry'] = intval($options['maxTry']);
		if($options['tryTimeout']) $params['tryTimeout'] = intval($options['tryTimeout']);
		if(array_key_exists('checkonly',$options)) $params['checkonly'] = true;
	}
	else $uri = filter_var($argv[1],FILTER_SANITIZE_URL);
}
else {
	$uri = filter_var($_REQUEST['uri'],FILTER_SANITIZE_URL); 	// запрос, переданный от nginx. Считаем, что это запрос тайла
}

//echo "Исходный uri=$uri; params:"; print_r($params);
//error_log("Исходный uri=$uri;");
if($uri) $img=getTile($uri,$params); 	// собственно, получение

session_write_close();
//ob_flush();
if(@$argv) {
	if($img===FALSE) { 	// тайла не было и он не был получен
		fwrite(STDOUT, '1');	// сообщим об этом, там разберутся
		return(1);
		//fwrite(STDOUT, '0');	// всё равно вернём ok, потому что иначе загрузка реально отсутствующих тайлов будет продолжаться вечно
		//return(0);
	}
	else {
		fwrite(STDOUT, '0');
		return(0);
	}
}

/* Функции работы с кешем

getTile($path) - получить файл из источника и положить в кеш
doBann($r,$bannedSourcesFileName) - забанить источник
function getResponceFiled($http_response_header,$respType) Возвращает массив полей http ответа, начинающихся с $respType

*/


function getTile($path,$params=array(),$getURLparams=array()) {
/* 
	Get tile from souce

Call to get tile from source, to asynchronous update cache.
Return image or NULL or FALSE and update cache.

Usage: $ php tilefromsource.php '/tiles/OpenTopoMap/11/1185/578.png'

Если получено 404 - сохраняет пустой тайл, в остальных случаях - переспрашивает.
Если принятый файл - в списке мусорных,сохраняем пустой

$params - массив с параметрами вообще. В основном - для переопределения переменных из params.php
После загрузки params.php из $params создаются переменные, переписывающие переменные из params.php

$getURLparams - массив с параметрами для передаяи функции getURL(), определённой в файле источника
вообще говоря - произвольный, но будем считать, что туда могут передаваться стандартные переменные 
со своим именем в качестве ключа
*/
/* Исторически require файла с описанием источника карты происходило в корне каждого скрипта
и global там вполне срабатывало. Но со временем это require переместилось в функции, и все файлы источников
использующие в функции getURL свои переменные через global -- сломались.
Можно использовать костыль getURLparams, но он, вроде, не для этого. А для чего -- я забыл...
Поэтому в здесь все (?) известные переменные из файла источника делаются глобальными принудительно.
С тех пор появился mapsourcesVariablesList.php, и можно завести переменные из файлов источников
естественным путём. Поэтому фактически эти переменные здесь не делаются глобальными: они уже существуют.
*/
// вообще, эта глобализация может боком выйти. Надо пересмотреть все источники карт
// на предмет использования глобальных переменных.
//global $ttl, $noTileReTry, $freshOnly, $ext, $ContentType, $minZoom, $maxZoom, $EPSG, $on403, $trash, $content_encoding, $trueTile;
require('params.php'); 	// пути и параметры
if($params) extract($params,EXTR_OVERWRITE);
//echo "maxTry=$maxTry; tryTimeout=$tryTimeout;\n"; print_r($params);
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
if($pos=strpos($mapSourcesName,'_COVER')) { 	// нужно показать покрытие, а не саму карту
	require("$mapSourcesDir/common_COVER"); 	// файл, описывающий источник тайлов покрытия, используемые ниже переменные - оттуда.
	$getURLparams['r'] = substr($mapSourcesName,0,$pos); 	// для tilesCOVER нужно указывать имя той карты, покрытие которой надо получить, без _COVER
	$getURLparams['tileCacheServerPath'] = $tileCacheServerPath; // 
}
else {
	// Инициализируем переменные, которые могут быть в файле источника карты
	require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
	require("$mapSourcesDir/$mapSourcesName.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда.
};
//echo "[getTile] ext=$ext;\n";
if(!$ext){
	if($path_parts['extension']) $ext = $path_parts['extension'];
	else $ext = 'png';
};
$fileName = "$tileCacheDir/$mapSourcesName$mapAddPath/$z/$x/$y.$ext";
$getURLparams['mapAddPath'] = $mapAddPath;
//echo "fileName=$fileName; <br>\n";
$newimg = FALSE; 	// исходная ситуация -- тайл получить не удалось
if (!$functionGetURL) { 	// нет функции для получения тайла	
	$msg = "tilefromsource.php getTile: No functionGetURL for $mapSourcesName. Will not receive a tile";
	echo "$msg\n";
	error_log($msg);	
	goto END;
}
// есть функция получения тайла
// определимся с наличием проблем связи и источника карты
$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName)); 	// считаем файл проблем
if(!$bannedSources) $bannedSources = array();
//echo "bannedSources:<pre>"; print_r($bannedSources); echo "</pre>";
if(!($params['checkonly']) and (time()-@$bannedSources[$mapSourcesName][0]-$noInternetTimeout)<0) goto END;;	// если таймаут из конфига не истёк

// Проблем связи и источника нет - будем получать тайл
eval($functionGetURL); 	// создадим функцию getURL
$tries = 1;
$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
$msg='';
do {
	$newimg = FALSE; 	// умолчально - тайл получить не удалось, ничего не сохраняем, пропускаем
	//echo "Параметры:<pre>"; print_r($getURLparams); echo "</pre>";
	//echo "tileCacheServerPath=$tileCacheServerPath;\n";
	$uri = getURL($z,$x,$y,$getURLparams); 	// получим url и массив с контекстом: заголовками, etc.
	//echo "Источник:<pre>"; print_r($uri); echo "</pre>\n";
	if(!$uri) {
		if($on403=='skip') {
			$msg = "tilefromsource.php getTile: $mapSourcesName no hawe url.";
			error_log($msg);
			goto END; 	// по каким-то причинам нет uri тайла, очевидно, картинки нет и не будет
		}
		else {	
			doBann($mapSourcesName,$bannedSourcesFileName,'No url'); 	// забаним источник 
			$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
			$msg = "tilefromsource.php getTile $tries's try: No url by life or No token";
			error_log($msg);
		}
		break; 	 // бессмысленно ждать, прекращаем получение тайла
	}
	// Параметры запроса
	if(is_array($uri))	list($uri,$opts) = $uri;
	if(!is_array($opts)) $opts = array();
	if(!@$opts['http']) {
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
	echo "\nGet tile from: $uri\n";
	$context = stream_context_create($opts); 	// таким образом, $opts всегда есть

	// Запрос - собственно, получаем файл
	$newimg = file_get_contents($uri, FALSE, $context); 	// 
	//echo "http_response_header:<pre>"; print_r($http_response_header); echo "</pre>\n";

	// Обработка проблем ответа
	if(!$newimg and !@$http_response_header) { 	 //echo "связи нет или Connection refused  ".$http_response_header[0]."<br>\n"; 	при 403 переменная не заполняется?
		if ($tries > $maxTry-1) { 	// сперва ждём
			doBann($mapSourcesName,$bannedSourcesFileName,'No internet connection or Connection refused'); 	// напоследок забаним источник
			$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
			$msg = "tilefromsource.php getTile $tries's try: No internet connection or Connection refused - do bann and go away";
			error_log($msg);
			goto END; 	 // бессмысленно ждать, уходим совсем
		}
	}
	elseif((strpos($http_response_header[0],'403') !== FALSE) or (strpos($http_response_header[0],'204') !== FALSE)) { 	// Forbidden or No Content
		if($on403=='skip') {
			$newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
			$msg = "tilefromsource.php getTile $tries's try: Save enpty tile by 403 Forbidden or No Content responce and on403==skip parameter";
			error_log($msg);
		}
		else {	
			doBann($mapSourcesName,$bannedSourcesFileName,'Forbidden'); 	// забаним источник 
			$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
			$msg = "tilefromsource.php getTile $tries's try: 403 Forbidden or No Content responce";
			error_log($msg);
		}
		break; 	 // бессмысленно ждать, прекращаем получение тайла
	}
	elseif((strpos($http_response_header[0],'404') !== FALSE) or (strpos($http_response_header[0],'416') !== FALSE)) { 	// файл не найден or Requested Range Not Satisfiable - это затейники из ЦГКИПД
		$newimg = NULL; 	// картинки нет, потому что её нет, сохраняем пустой тайл.
		$msg = "tilefromsource.php getTile $tries's try: 404 (or similar) Not Found and go away";
		error_log($msg);
		break; 	 // бессмысленно ждать, прекращаем получение тайла
	}
	elseif(strpos($http_response_header[0],'301') !== FALSE) { 	// куда-то перенаправляли, по умолчанию в $opts - следовать
		foreach($http_response_header as $header) {
			if((substr($header,0,4)=='HTTP') AND (strpos($header,'200') !== FALSE)) break; 	// файл получен, перейдём к обработке
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'404') !== FALSE)) { 	// файл не найден.
				$newimg = NULL;
				$msg = "tilefromsource.php getTile $tries's try: 404 Not Found and go away";
				error_log($msg);
				break 2; 	// бессмысленно ждать, прекращаем получение тайла
			}
			elseif((substr($header,0,4)=='HTTP') AND ((strpos($header,'403') !== FALSE) or ((strpos($http_response_header[0],'204') !== FALSE)))) { 	// Forbidden.
				if($on403=='skip') {
					$newimg = NULL; 	// картинки не будет, сохраняем пустой тайл. $on403 - параметр источника - что делать при 403. Умолчально - ждать
					$msg = "tilefromsource.php getTile $tries's try: 403 Forbidden or No Content responce and on403==skip parameter";
					error_log($msg);
				}
				else {
					doBann($mapSourcesName,$bannedSourcesFileName,'Forbidden'); 	// забаним источник 
					$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
					$msg = "tilefromsource.php getTile $tries's try: 403 Forbidden or No Content responce - do bann";
					error_log($msg);
				}
				break 2; 	 // бессмысленно ждать, прекращаем получение тайла
			}
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'503') !== FALSE)) { 	// Service Unavailable
				if ($tries > $maxTry-1) { 	// ждём
					doBann($mapSourcesName,$bannedSourcesFileName,'Service Unavailable'); 	// напоследок забаним источник
					$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
					$msg = "tilefromsource.php getTile $tries's try: 503 Service Unavailable responce - do bann and go away";
					error_log($msg);
					goto END; 	 // бессмысленно ждать, уходим совсем
				}
			}
		}
	}
	// Обработка проблем полученного
	if($http_response_header){
		$in_mime_type = trim(substr(end(getResponceFiled($http_response_header,'Content-Type')),13)); 	// нужно последнее вхождение - после всех перенаправлений
		//echo "in_mime_type=$in_mime_type;\n";
		//echo "trash "; print_r($trash); echo "\n";
		if($in_mime_type) { 	// mime_type присланного сообщили
			if(isset($mime_type)) { 	// mime_type того, что должно быть, указан в конфиге источника
				if($in_mime_type == $mime_type) { 	// mime_type присланного совпадает с требуемым
					// возможно, это можно принять
					if(@$globalTrash) { 	// имеется глобальный список ненужных тайлов
						if($trash) $trash = array_merge($trash,$globalTrash);
						else $trash = $globalTrash;
					}
					if(@$trash) { 	// имеется список ненужных тайлов
						$imgHash = hash('crc32b',$newimg);
						if(in_array($imgHash,$trash,TRUE)) { 	// принятый тайл - мусор, TRUE - для сравнения без преобразования типов
							$msg = 'tilefromsource.php getTile: tile in trash list';
							error_log($msg);
							$newimg = NULL; 	// тайл принят нормально, но он мусор, сохраним пустой тайл
							break; 	// прекращаем попытки получить
						}
					}
					break; 	// всё нормально, тайл получен
				}
				else { 	// mime_type присланного не совпадает с требуемым
					$msg = "tilefromsource.php getTile $tries's try: Reciewed $in_mime_type, but expected $mime_type. Skip, continue.";
					error_log($msg);
					$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем, продолжаем попытки получить
				}
			}
			else { 	// требуемый mime_type в конфиге не указан
				if ((substr($in_mime_type,0,5)=='image') or (substr($in_mime_type,-10)=='x-protobuf')) { 	// тайл - картинка или векторный тайл
					// возможно, это можно принять
					if(@$globalTrash) { 	// имеется глобальный список ненужных тайлов
						if($trash) $trash = array_merge($trash,$globalTrash);
						else $trash = $globalTrash;
					}
					if(@$trash) { 	// имеется список ненужных тайлов
						$imgHash = hash('crc32b',$newimg);
						if(in_array($imgHash,$trash,TRUE)) { 	// принятый тайл - мусор, TRUE - для сравнения без преобразования типов
							$msg = 'tilefromsource.php getTile: tile in trash list';
							error_log($msg);
							$newimg = NULL; 	// тайл принят нормально, но он мусор, сохраним пустой тайл
							break; 	// прекращаем попытки получить
						}
					}
					break; 	// всё нормально, тайл получен
				}
				else { 	// получен не тайл или непонятный тайл
					if (substr($in_mime_type,0,4)=='text') { 	// текст. Файла нет или не дадут. Но OpenTopo потом даёт
						$msg = "tilefromsource.php getTile $tries's try: server return '{$http_response_header[0]}' and text instead tile: '$newimg'";
						error_log($msg);
						//error_log("$uri: http_response_header:".implode("\n",$http_response_header));
						$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
					}
					else {
						$msg = "tilefromsource.php getTile $tries's try: No tile and unknown responce";
						error_log($msg);
						$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
					}
				}
			}
		}
		else { 	// mime_type присланного не сообщили
			$in_mime_type = finfo_buffer($file_info,$newimg); 	// определим mime_type присланного
			if ((substr($in_mime_type,0,5)=='image') or (substr($in_mime_type,-6)=='x-gzip')  or (substr($in_mime_type,-10)=='x-protobuf')) { 	//  тайл - картинка или, возможно, векторный тайл, хотя gzip ни о чём не говорит, а x-protobuf так не определяется.
				// возможно, это можно принять
				if(@$globalTrash) { 	// имеется глобальный список ненужных тайлов
					if($trash) $trash = array_merge($trash,$globalTrash);
					else $trash = $globalTrash;
				}
				if(@$trash) { 	// имеется список ненужных тайлов
					$imgHash = hash('crc32b',$newimg);
					if(in_array($imgHash,$trash,TRUE)) { 	// принятый тайл - мусор, TRUE - для сравнения без преобразования типов
						$msg = 'tilefromsource.php getTile: tile in trash list';
						error_log($msg);
						$newimg = NULL; 	// тайл принят нормально, но он мусор, сохраним пустой тайл
						break; 	// прекращаем попытки получить
					}
				}
				break; 	// всё нормально, тайл получен
			}
			else { 	// получен не тайл или непонятный тайл
				if (substr($in_mime_type,0,4)=='text') { 	// текст. Файла нет или не дадут. Но OpenTopo потом даёт
					$msg = "tilefromsource.php getTile $tries's try: $newimg";
					error_log($msg);
					//error_log("$uri: http_response_header:".implode("\n",$http_response_header));
					$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
				}
				else {
					$msg = "tilefromsource.php getTile $tries's try: No tile and unknown responce";
					error_log($msg);
					$newimg = FALSE; 	// тайл получить не удалось, ничего не сохраняем, пропускаем
				}
			}
		}
	}	// какой-нибудь http_response_header будет, если коммуникация состоялась. Его не будет только при отсутствии интернета.
	// Тайла не получили, надо подождать
	$tries++;
	if ($tries > $maxTry) {	// Ждать больше нельзя
		$newimg = FALSE; 	// Тайла не получили
		doBann($mapSourcesName,$bannedSourcesFileName,'Many tries'); 	// забаним источник
		$msg = "tilefromsource.php getTile $tries's try: no tile by max try - do bann and go away";
		error_log($msg);
		//break;
		goto END; 	 // бессмысленно ждать, уходим совсем
	}
	sleep($tryTimeout);
} while (TRUE); 	// 

END:

if($newimg !== FALSE) {	// тайл получен
	//echo "functionPrepareTileFile: |$functionPrepareTileFile|\n";
	if($functionPrepareTileFile) {
		eval($functionPrepareTileFile);	// определим функцию обработки файла. Если оно обломится - так и надо.
		//echo "Порежем файл $z,$x,$y,$ext на тайлы \n";
		$newimg = prepareTileFile($newimg,$z,$x,$y,$ext);
	};
};

if($params['checkonly']){	// надо только проверить, скачался ли правильный файл
	echo "checkonly mode: no save any files\n";
	if($newimg === FALSE) echo "No tile recieved\n$msg\n\n";
	elseif($newimg === NULL)  echo "Recieved a bad tile\n$msg\n\n";
	elseif($trueTile and $z==$trueTile[0] and $x==$trueTile[1] and $y=$trueTile[2]){	// мы знаем, какой файл правильный
		if(is_array($newimg)) $img=$newimg[0][0];	// первый из нарезки
		else $img=$newimg;
		$hash = hash('crc32b',$img);
		if($hash==$trueTile[3]){	// тайл такой, какой нужно
			echo "\nThe tile is true\n\n";
		}
		else{
			echo "\nThe tile is not true, must be {$trueTile[3]}, recieved $hash\n$msg\n\n";
			$newimg = FALSE;
		};
	};
}
else {
	// сохраним тайл
	//if($newimg !== FALSE) {	// теперь тайл получен, возможно, пустой в случае 404 или мусорного тайла
	if(($newimg !== FALSE) and (($newimg !== NULL) or (($newimg === NULL) and (!file_exists($fileName))))) {	// теперь тайл получен, возможно, пустой в случае 404 или мусорного тайла, если он пустой - запишем только в том случае, если файла нет
		if(!is_array($newimg)) $newimg = array(array($newimg,"$z/$x/$y.$ext"));
		foreach($newimg as $imgInfo){
			$fileName = "$tileCacheDir/$mapSourcesName$mapAddPath/".$imgInfo[1];
			//echo "сохраняем тайл $fileName с mime-type $mime_type\n";
			$umask = umask(0); 	// сменим на 0777 и запомним текущую
			//@mkdir(dirname($fileName), 0755, true);
			@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
			//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
			if( $fp = @fopen($fileName, "w")) {
				fwrite($fp, $imgInfo[0]);
				fclose($fp);
				@chmod($fileName,0666); 	// чтобы при запуске от другого юзера была возможность заменить тайл, когда он протухнет
				error_log("tilefromsource.php getTile $tries's try: Saved ".strlen($imgInfo[0])." bytes to $fileName");	
			};
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
		};
	};
};

// Обслужим источник
if(($newimg !== FALSE) and @$bannedSources[$mapSourcesName]) { 	// снимем проблемы с источником, получили мы тайл или нет
	unset($bannedSources[$mapSourcesName]); 	// снимем проблемы с источником
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	file_put_contents($bannedSourcesFileName, serialize($bannedSources));
	@chmod($bannedSourcesFileName,0666); 	// чтобы при запуске от другого юзера была возаможность 
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	error_log("tilefromsource.php getTile $tries's try: $mapSourcesName unbanned!");
}

if (!$functionGetURL) return true;	// иначе loader будет вечно запрашивать отсутствующий тайл
return($newimg);
} // end function getTile


function doBann($r,$bannedSourcesFileName,$reason='') {
/* Банит источник */
//error_log(print_r($http_response_header,TRUE));
//error_log("doBann: bannedSources ".print_r($bannedSources,TRUE));

$curr_time = time();
$bannedSources[$r][0] = $curr_time; 	// отметим проблемы с источником
$bannedSources[$r][1] = $reason; 	// 
//echo "bannedSources:<pre>"; print_r($bannedSources); echo "</pre>";
//echo serialize($bannedSources)."<br>\n";
$umask = umask(0); 	// сменим на 0777 и запомним текущую
file_put_contents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
//quickFilePutContents($bannedSourcesFileName, serialize($bannedSources)); 	// запишем файл проблем
@chmod($bannedSourcesFileName,0666); 	// чтобы при запуске от другого юзера была возаможность 
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
//error_log("doBann: bannedSources ".print_r($bannedSources,TRUE));
error_log("tilefromsource.php doBann: $r banned at ".gmdate("D, d M Y H:i:s", $curr_time)." by $reason reason!");
} // end function doBann

function getResponceFiled($http_response_header,$respType) {
/* Возвращает массив полей http ответа, начинающихся с $respType
*/
return array_values(array_filter($http_response_header,function ($str) use($respType){return (strpos($str,$respType) === 0);} ));
} // end function getResponceFiled

?>
