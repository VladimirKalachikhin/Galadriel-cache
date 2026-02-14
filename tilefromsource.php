<?php
/* Утилита командной строки! Не предназначена для запроса http!
Совершает один запрос к внешнему источнику, и получает от него картинку.
Эта картинка - один тайл или прямоугольник из нескольких тайлов. В последнем случае в параметрах
карты должна быть функция $prepareTileImage, которая разрезает картинку на тайлы. 
Тайлы сохраняются в локальное хранилище функцией $saveTileToStorage, которая может быть пользовательской.
Штатная функция $saveTileToStorage сохраняет тайл как файл в структуре каталогов r/z/x/y.ext

Параметр --options должен содержать json с параметрами "ключ":значение
Понимаются следующие ключи:

$options['prepareTileImg'] bool - применять ли функцию $prepareTileImgAfterRecieve, если
	она есть в описании карты

$options['getURLoptions'] = $getURLoptions - переменный, которые будут добавлены в
	массив $getURLoptions из конфигурации карты

Считается, что тайл от внешнего источника никогда не запрашивается напрямую кем-либо:
он сперва скачивается в кеш, а потом берётся оттуда.

Коды возврата:
Return codes:
Преходящие проблемы
Temporary problems
1	Source is banned
2	Retrieve max tries, but nothing was received
3	The tile is already loading
4	Tile is not true

Критические проблемы
Critical problems
8	No url for do retrive from source
9	403 Forbidden or No Content responce
10	404 Not Found (or similar)
11	404 Not Found (or similar) on 301 Moved Permanently
12	403 Forbidden or No Content responce on 301 Moved Permanently
13	Bad or unknown mime-type
14	Text response

Ошибки
Errors
32	Bad command line parameters
33	No map description file present
34	No get from source procedure
35	No storage procedure
36	Require check tile, but no tile info
37	Storage error

It's cli only!
Makes one request to an external source, and receives a picture from it.
This image is a single tile or rectangle consisting of several tiles. In the latter case, the
map parameters should have a $prepareTileImage function that cuts the image into tiles.
Tiles are saved to local storage by the $saveTileToStorage function, which can be custom.
The standard $saveTileToStorage function saves the tile as a file in the directory structure r/z/x/y.ext

It is assumed that a tile from an external source is never requested directly by anyone:
it is first downloaded to the cache, and then taken from there.

The --options parameter must contain json with the "key":value content.
The following keys are understood:

$options['prepareTileImg'] bool - whether to use the $prepareTileImgAfterRecieve function if
	it is in the map description.

$options['getURLoptions'] = $getURLoptions - variables to be added to the
	$getURLoptions array from the map configuration
*/

/*
\e[3mtilefromsource.php\e[0m		italic
\033[1mtilefromsource.php\033[0m	bold

echo -e '\033[1mYOUR_STRING\033[0m'
Explanation:
    echo -e - The -e option means that escaped (backslashed) strings will be interpreted
    \033 - escaped sequence represents beginning/ending of the style
    lowercase m - indicates the end of the sequence
    1 - Bold attribute (see below for more)
    [0m - resets all attributes, colors, formatting, etc.

The possible integers are:
    0 - Normal Style
    1 - Bold
    2 - Dim
    3 - Italic
    4 - Underlined
    5 - Blinking
    7 - Reverse
    8 - Invisible

*/
chdir(__DIR__); // задаем директорию выполнение скрипта
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$clioptions = getopt("z:x:y:r:",array('maxTry:','tryTimeout:','checkonly','options:'));
//echo "clioptions: "; var_dump($clioptions); echo "\n";
if(!$clioptions) {
	echo "
Getting a tile from a specified source
Usage:
php tilefromsource.php  -z(int)tile_ZOOM -x(int)tile_X -y(int)tile_Y -r(str)MapName [--maxTry=(int)N] [--tryTimeout=(int)Sec] [--checkonly] [--options=(json){'key':value}]
or
php tilefromsource.php  -r(str)MapName --checkonly
Example:
php tilefromsource.php  -z13 -x5204 -y2908 -rOpenTopoMap --maxTry=5 --tryTimeout=30 --checkonly

"
	;
	exit(32);
};
$r = filter_var($clioptions['r'],FILTER_SANITIZE_URL);
if(!$r){
	error_log("tilefromsource.php - Impossible: No map name");
	exit(32);
};
$cnt = 0;
if(array_key_exists('x',$clioptions)) $cnt++;
if(array_key_exists('y',$clioptions)) $cnt++;
if(array_key_exists('z',$clioptions)) $cnt++;

if(isset($clioptions['options'])) {
	$options = json_decode($clioptions['options'],true);
	if($options === null){
		error_log("tilefromsource.php - getted options, but false decode JSON. JSON set to empty.");
		$options = array();
	};
}
else $options = array();

//echo "tilefromsource.php - options: "; print_r($options); echo "\n"; exit;
$checkonly = $options['checkonly'];
if(array_key_exists('checkonly',$clioptions)) $checkonly = true;
$options['checkonly'] = $checkonly;

if(	($cnt<3 and $cnt>0)
	or ( !$checkonly
		and (($clioptions['x']==='') 
			or ($clioptions['y']==='') 
			or ($clioptions['z']==='') 
			or ($clioptions['x']===null) 
			or ($clioptions['y']===null) 
			or ($clioptions['z']===null)
		)
	)
) {
	error_log("tilefromsource.php - Impossible: Incorrect tile info: {$clioptions['r']}/{$clioptions['z']}/{$clioptions['x']}/{$clioptions['y']}");		
	exit(32);
};

require('fIRun.php'); 	// 
if(IRun()){
	error_log("tilefromsource.php - The tile {$clioptions['r']}/{$clioptions['z']}/{$clioptions['x']}/{$clioptions['y']} is already loading");
	exit(0);
};

$prepareTileImg = false;
if($options['prepareTileImg']) $prepareTileImg = true;

require('params.php'); 	// пути и параметры
if(!$phpCLIexec) $phpCLIexec = trim(explode(' ',trim(shell_exec("ps -p ".(getmypid())." -o command=")))[0]);	// из PID системной командой получаем командную строку и берём первый отделённый пробелом элемент. Считаем, что он - команда запуска php. Должно работать и в busybox.

// Переопределение переменных из params.php
if($clioptions['maxTry']) $maxTry = intval($clioptions['maxTry']);
if($clioptions['tryTimeout']) $tryTimeout = intval($clioptions['tryTimeout']);

require 'fTilesStorage.php';	// стандартные функции получения/записи тайла из локального хранилища
require 'mapsourcesVariablesList.php';	// умолчальные переменные и функции описания карты, полный комплект
require 'fCommon.php';	// функции, используемые более чем в одном крипте

//	Каталог описаний источников карт, в файловой системе
$mapSourcesDir = 'mapsources'; 	// map sources directory, in filesystem.

// Параметры карты
if(!(@include "$mapSourcesDir/$r.php")){
	error_log("tilefromsource.php - Impossible: Map description file not found");
	exit(33);
};
if(!$getURL){
	error_log("tilefromsource.php - Impossible: No get from source procedure");
	exit(34);
};
if(!$putTile){
	error_log("tilefromsource.php - Impossible: No storage procedure");
	exit(35);
};

if($checkonly and ($cnt == 0)) {	// требуется только проверка тайла, и какого именно - не указано.
	if($trueTile){	// Тогда берём свойства правильного из описания карты
		$x = $trueTile[1];
		$y = $trueTile[2];
		$z = $trueTile[0];
		if(is_array($mapTiles)){	// карта многослойная
			if(!isset($options['layer'])) {
				if(isset($trueTile[4])) $options['layer'] = $trueTile[4];
				else $options['layer'] = 0;	// правильный тайл - у нижнего слоя
			};
		};
	}
	else {	// Но негде взять адрес тайла
		error_log("tilefromsource.php - Impossible: Require check tile, but no tile info");
		exit(36);
	};
}
else {
	$x = intval($clioptions['x']);
	$y = intval($clioptions['y']);
	$z = intval($clioptions['z']);
};

if(($z<$minZoom)or($z>$maxZoom)){
	error_log("tilefromsource.php - Impossible: Request is out of zoom");
	exit(0);	// тайл не стали получать, но его и не должно быть - норма.
};
if(!checkInBounds($z,$x,$y,$bounds)){	// тайл вообще должен быть?
	error_log("tilefromsource.php - Impossible: Request is out of map bounds");
	exit(0);	// тайл не стали получать, но его и не должно быть - норма.
};
//echo "maxTry=$maxTry; tryTimeout=$tryTimeout; checkonly=$checkonly; options:"; print_r($options); echo "\n";

// определимся с наличием проблем связи и источника карты
$bannedSourcesFileName = "$jobsDir/bannedSources";
clearstatcache(true,$bannedSourcesFileName);
$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName)); 	// считаем файл проблем
if(!$bannedSources) $bannedSources = array();
if(!$checkonly										// проверяем и забаненный источник
	and ((time()-@$bannedSources[$r][0])<$noInternetTimeout)	// если срок бана из конфига не истёк
) {
	error_log("tilefromsource.php - Unsuccessfully: Source $r is banned");
	exit(1);	// banned
};
// Проблем связи и источника нет - будем получать тайл
if(isset($options['getURLoptions'])) {
	if(!isset($getURLoptions)) $getURLoptions = array();
	$getURLoptions = array_merge($getURLoptions,$options['getURLoptions']);
};
// Загрузчик вообще не интересуется параметрами, поэтому, если вызвали из загрузчика, параметры надо установить.
if((!isset($getURLoptions['layer']) or is_null($getURLoptions['layer'])) and isset($options['layer'])) {
	$getURLoptions['layer'] = $options['layer'];
};
//echo "getURLoptions:"; print_r($getURLoptions); echo "\n";

for($tries=1;$tries<=$maxTry;sleep($tryTimeout),$tries++) {
	$newimg = FALSE; 	// умолчально - тайл получить не удалось, ничего не сохраняем, пропускаем
	//echo "Параметры:<pre>"; print_r($getURLoptions); echo "</pre>";
	$uri = $getURL($z,$x,$y,$getURLoptions); 	// получим url и массив с контекстом: заголовками, etc.
	//echo "Источник:<pre>"; print_r($uri); echo "</pre>\n"; exit;
	if(is_array($uri))	list($uri,$opts) = $uri;
	if(!is_array($opts)) $opts = array();
	if(!$uri) { 	// по каким-то причинам нет uri тайла, очевидно, картинки нет и не будет
		error_log("tilefromsource.php - Impossible: No url for do retrive from source");
		exit(8);	// No url
	};
	// Параметры запроса
	if(!@$opts['http']) {
		$opts['http']=array(
			'method'=>"GET",
			'follow_location'=>1
		);
	};
	if(!@$opts['ssl']) { 	// откажемся от проверок ssl сертификатов, потому что сертификатов у нас нет
		$opts['ssl']=array(
			"verify_peer"=>FALSE,
			"verify_peer_name"=>FALSE
		);
	};
	if(!@$opts['http']['proxy'] AND @$globalProxy) { 	// глобальный прокси, строка из params.php
		$opts['http']['proxy']=$globalProxy;
		$opts['http']['request_fulluri']=TRUE;
	};
	if(!@$opts['http']['timeout']) { 
		$opts['http']['timeout'] = (float)$getTimeout;	// таймаут ожидания получения тайла, сек. params.php
		if(!$opts['http']['timeout']) $opts['http']['timeout'] = 30;
	};
	if(!isset($opts['http']['follow_location'])) { 
		$opts['http']['follow_location'] = 1;	// Follow Location header redirects.
	};
	echo "Get tile from: $uri\n";
	//echo "with opts :"; print_r($opts); echo "\n";
	$context = stream_context_create($opts); 	// таким образом, $opts всегда есть

	// Запрос - собственно, получаем файл
	$newimg = @file_get_contents($uri, FALSE, $context); 	// 
	//echo "http_response_header:"; print_r($http_response_header); echo "s\n";
	
	// Обработка проблем ответа
	if(!$newimg and !@$http_response_header) { 	 //echo "связи нет или Connection refused  ".$http_response_header[0]."<br>\n"; 	при 403 переменная не заполняется?
		continue;
	}
	elseif((strpos($http_response_header[0],'403') !== FALSE) or (strpos($http_response_header[0],'204') !== FALSE)) { 	// Forbidden or No Content
		switch($on403){
		case 'skip':
			$newimg = NULL; 	// картинки не будет, сохраняем пустой тайл.
			error_log("tilefromsource.php - retrieve $tries's try: will be an enpty tile by 403 Forbidden or No Content responce and on403==skip parameter");
			break 2;
		case 'wait':
			doBann($r,$bannedSourcesFileName,'Forbidden'); 	// забаним источник 
			error_log("tilefromsource.php - retrieve $tries's try: 403 Forbidden or No Content responce, do bann source");
			exit(9);	// 403 Forbidden бессмысленно ждать, прекращаем получение тайла
		case 'done':
			error_log("tilefromsource.php - retrieve $tries's try: 403 Forbidden or No Content responce");
			exit(9);	// бессмысленно ждать, прекращаем получение тайла
		};
	}
	elseif((strpos($http_response_header[0],'404') !== FALSE) or (strpos($http_response_header[0],'416') !== FALSE)) { 	// файл не найден or Requested Range Not Satisfiable - это затейники из ЦГКИПД
		switch($on404){
		case 'skip':
			$newimg = NULL; 	// картинки не будет, сохраняем пустой тайл.
			error_log("tilefromsource.php - retrieve $tries's try: Save enpty tile by 404 Not Found (or similar)");
			break 2;
		case 'wait':
			doBann($r,$bannedSourcesFileName,'Forbidden'); 	// забаним источник 
			error_log("tilefromsource.php - retrieve $tries's try: 404 Not Found (or similar), do bann source");
			exit(10);	// бессмысленно ждать, прекращаем получение тайла
		case 'done':
			error_log("tilefromsource.php - retrieve $tries's try: 404 (or similar). Not Found and go away");
			exit(10);	// бессмысленно ждать, прекращаем получение тайла
		};
	}
	elseif(strpos($http_response_header[0],'301') !== FALSE) { 	// куда-то перенаправляли, по умолчанию в $opts - следовать
		foreach($http_response_header as $header) {
			if((substr($header,0,4)=='HTTP') AND (strpos($header,'200') !== FALSE)) break; 	// файл получен, перейдём к обработке
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'404') !== FALSE)) { 	// файл не найден.
				switch($on404){
				case 'skip':
					$newimg = NULL; 	// картинки не будет, сохраняем пустой тайл.
					error_log("tilefromsource.php - retrieve $tries's try: Save enpty tile by 404 Not Found (or similar) on 301 Moved Permanently");
					break 3;
				case 'wait':
					doBann($r,$bannedSourcesFileName,'Forbidden'); 	// забаним источник 
					error_log("tilefromsource.php - retrieve $tries's try: 404 Not Found (or similar) on 301 Moved Permanently, do bann source");
					exit(11);	// бессмысленно ждать, прекращаем получение тайла
				case 'done':
					error_log("tilefromsource.php - retrieve $tries's try: 404 (or similar) on 301 Moved Permanently. Not Found and go away");
					exit(11);	// бессмысленно ждать, прекращаем получение тайла
				};
			}
			elseif((substr($header,0,4)=='HTTP') AND ((strpos($header,'403') !== FALSE) or ((strpos($http_response_header[0],'204') !== FALSE)))) { 	// Forbidden.
				switch($on403){
				case 'skip':
					$newimg = NULL; 	// картинки не будет, сохраняем пустой тайл.
					error_log("tilefromsource.php - retrieve $tries's try: Save enpty tile by 403 Forbidden or No Content responce on 301 Moved Permanently and on403==skip parameter");
					break 3;
				case 'wait':
					doBann($r,$bannedSourcesFileName,'Forbidden'); 	// забаним источник 
					error_log("tilefromsource.php - retrieve $tries's try: 403 Forbidden or No Content responce on 301 Moved Permanently, do bann source");
					exit(12);	// бессмысленно ждать, прекращаем получение тайла
				case 'done':
					error_log("tilefromsource.php - retrieve $tries's try: 403 Forbidden or No Content responce on 301 Moved Permanently");
					exit(12);	// бессмысленно ждать, прекращаем получение тайла
				};
			}
			elseif((substr($header,0,4)=='HTTP') AND (strpos($header,'503') !== FALSE)) { 	// Service Unavailable
				continue;
			};
		};
	};

	// Обработка проблем полученного
	$in_length = intval(substr(end(getResponceFiled($http_response_header,'Content-Length')),15));
	//echo "Получено ".(strlen($newimg))." байт, дложно быть $in_length;\n";
	if($in_length and (strlen($newimg)!=$in_length)){	// Было получено не столько (меньше, чем) должно было быть. Это делает cloudflare. При отсутствии Content-Length будет 0.
		error_log("tilefromsource.php - retrieve $tries's try: Reciewed ".(strlen($newimg))." bytes, but must be $in_length bytes.");
		$newimg = FALSE; 	// тайл получить не удалось
		continue;	// попытаемся получить снова?
	};
	$in_mime_type = trim(substr(end(getResponceFiled($http_response_header,'Content-Type')),13)); 	// нужно последнее вхождение - после всех перенаправлений. Если Content-Type вообще нет - будет пустая строка.
	//echo "in_mime_type=$in_mime_type;\n";
	//echo "trash "; print_r($trash); echo "\n";
	if($in_mime_type) { 	// mime_type присланного сообщили
		if(isset($mime_type)) { 	// mime_type того, что должно быть, указан в конфиге источника
			if($in_mime_type != $mime_type) { 	// mime_type присланного не совпадает с требуемым
				error_log("tilefromsource.php - retrieve $tries's try: Reciewed $in_mime_type, but expected $mime_type.");
				exit(13);	// прекращаем получение тайла
			};
			break; 	// тайл получен
		}
		else { 	// требуемый mime_type в конфиге не указан
			if((substr($in_mime_type,0,5)!='image') and (substr($in_mime_type,-10)!='x-protobuf')) { 	// тайл - не картинка и не векторный тайл
				if (substr($in_mime_type,0,4)=='text') { 	// текст. Файла нет или не дадут. Но OpenTopo потом даёт
					error_log("tilefromsource.php - retrieve $tries's try: server return '{$http_response_header[0]}' and text instead tile: '$newimg'");
					//error_log("$uri: http_response_header:".implode("\n",$http_response_header));
				}
				else {
					error_log("tilefromsource.php - retrieve $tries's try: No tile and unknown responce: {$http_response_header[0]}");
				};
				exit(14);	// прекращаем получение тайла
			};
			break; 	// тайл получен
		};
	}
	else { 	// mime_type присланного не сообщили
		$file_info = finfo_open(FILEINFO_MIME_TYPE); 	// подготовимся к определению mime-type
		$in_mime_type = finfo_buffer($file_info,$newimg); 	// определим mime_type присланного
		if((substr($in_mime_type,0,5)!='image') and (substr($in_mime_type,-10)!='x-protobuf')) { 	// тайл - не картинка и не векторный тайл
			if (substr($in_mime_type,0,4)=='text') { 	// текст. Файла нет или не дадут. Но OpenTopo потом даёт
				error_log("tilefromsource.php - retrieve $tries's try: server return '{$http_response_header[0]}' and text instead tile: '$newimg'");
				//error_log("$uri: http_response_header:".implode("\n",$http_response_header));
			}
			else {
				error_log("tilefromsource.php - retrieve $tries's try: No tile and unknown responce: {$http_response_header[0]}");
			};
			exit(13);	// прекращаем получение тайла
		};
		break; 	// тайл получен
	};
};	// конец попыток скачать файл
//echo "Было $tries попыток из $maxTry\n";
if(!$newimg and ($tries==($maxTry+1))){
	error_log("tilefromsource.php - retrieve max tries, but nothing was received.");
	exit(2);
};

// Картинак получена, возможно, она null
//echo "Получена картинка размером ".(strlen($newimg))." байт.\n"; //var_dump($newimg);
// Не мусор ли оно
//echo "tilefromsource.php - trash: ";print_r($trash);echo"\n";
if(@$globalTrash) { 	// имеется глобальный список ненужных тайлов
	if($trash) $trash = array_merge($trash,$globalTrash);	// имеется локальный список ненужных тайлов
	else $trash = $globalTrash;
};
if(@$trash) { 	// имеется список ненужных тайлов
	$imgHash = hash('crc32b',$newimg);
	//echo "tilefromsource.php - imgHash=$imgHash;\n";
	if(in_array($imgHash,$trash,TRUE)) { 	// принятый тайл - мусор, TRUE - для сравнения без преобразования типов
		error_log("tilefromsource.php - tile in trash list");
		$newimg = NULL; 	// тайл принят нормально, но он мусор, сохраним пустой тайл
	};
};

// 	Если указано обработать картинку по получению и есть функция обработки - применим её
if($prepareTileImg and $prepareTileImgAfterRecieve) {
	$newimg = $prepareTileImgAfterRecieve($newimg,$z,$x,$y,$ext);
};
//echo "Картинка после prepareTileImgAfterRecieve имеет размер ".(strlen($newimg))." байт.\n";

$exitCode = 0;	// успешно
// нужно только проверить скачиваемость тайла, и, возможно, совпадение полученного и
// правильного тайла, если $trueTile - массив со свойствами правильного тайла

if(!is_array($newimg)) $newimg = array(array($newimg,"$z/$x/$y.$ext"));
//echo "options:"; print_r($options); echo "\n";
if($checkonly){	// надо только проверить, скачался ли правильный файл
	echo "\n\033[1mtilefromsource.php\033[0m checkonly mode: Success, the tile may be recieve, but not store\n";
	if(!$trueTile) $trueTile = true;
	list($res,$msg) = $putTile($r,$newimg,$trueTile,$options);
	// tilefromsource вызывается в checkSources.php для проверки доступности всех карт, 
	// поэтому ответ должен быть вменяем
	if(!$res) $exitCode = 4;
	if(trim($msg)) echo "$msg\n";	
	else echo "\n"; 
}
else {
	// сохраним тайл
	list($res,$msg) = $putTile($r,$newimg,null,$options);
	if(!$res) $exitCode = 37;
	if(trim($msg)) error_log("tilefromsource.php - $msg");
};

// Обслужим источник
if(@$bannedSources[$r]) { 	// снимем проблемы с источником, нормальный тайл или нет
	unset($bannedSources[$r]); 	// снимем проблемы с источником
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	file_put_contents($bannedSourcesFileName, serialize($bannedSources));
	@chmod($bannedSourcesFileName,0666); 	// чтобы при запуске от другого юзера была возаможность 
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	error_log("tilefromsource.php - getTile $tries's try: $z unbanned!");
};

exit($exitCode);



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
error_log("tilefromsource.php - [doBann]: $r banned at ".gmdate("D, d M Y H:i:s", $curr_time)." by $reason reason!");
} // end function doBann

function getResponceFiled($http_response_header,$respType) {
/* Возвращает массив полей http ответа, начинающихся с $respType
*/
return array_values(array_filter($http_response_header,function ($str) use($respType){return (strpos($str,$respType) === 0);} ));
} // end function getResponceFiled



?>
