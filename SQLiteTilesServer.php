<?php
/**/
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта
require('fIRun.php'); 	// 
require('./params.php'); 	// пути и параметры (без указания пути оно сперва ищет в include_path, а он не обязан начинаться с .)
require 'fTilesStorage.php';	// стандартные функции получения тайла из локального источника

$SocketTimeout = 60;	// демон умрёт через сек.
//$SocketTimeout = null;	// демон не умрёт никогда

$r = filter_var($argv[1],FILTER_SANITIZE_FULL_SPECIAL_CHARS);	// один параметр -- имя карты без расширения
if(!$r) {
	echo "Map name required.\n";
	exit(1);
};
if(IRun()) {
	echo "I'm already running, exiting.\n"; 
	exit(1);
};

$DBfile = "$tileCacheDir/$r.mbtiles";
if(!file_exists($DBfile)) {
	error_log("Data file $DBfile not found.");
	// Прочитаем описание карты, когда ещё ничего, потому что там может быть вызов SQLiteWrapper
	// который обратится сюда же, и получится странное. А так - не получится, просто будет большая задержка.
	// По этим данным заполняется таблица metadata новой базы данных
	require 'mapsourcesVariablesList.php';	// умолчальные переменные и функции описания карты, полный комплект
	@include "$mapSourcesDir/$r.php";	// собственно, оно не надо. А здесь - чтобы сохранить соглашение о глобальности.
	if(!createDB($r)) exit(1);	// создадим отсутствующую базу данных, даже если она нам и не понадобится
};

try{
	$db = new SQLite3($DBfile,SQLITE3_OPEN_READWRITE);
}
catch(Exception $e){
	error_log("ERROR with open $DBfile: ".$e->getMessage());
	exit(1);
};
echo "Daemon started for '$r' map in '$tileCacheDir' directory\n";


// Начало строки запроса тайла
// По-умолчанию - запрос в соответствии со спецификацией mbtiles,
// однако, если обнаруживается, что база создана нами, то там есть timestamp,
// получение которого и включается в запрос
$getTileQuery = "SELECT tile_data FROM tiles WHERE ";
$isGaladrielCacheScheme = false;
// Сразу получим metadata из файла
$metadata = array();
$res = $db->query("
SELECT name,value
FROM metadata
");
while($data = $res->fetchArray(SQLITE3_ASSOC)){
	switch($data['name']){
	case 'name':
		if(!$metadata['humanName']) $metadata['humanName'] = array('en'=>$data['value']);	// эти придурки традиционно не знают о существовании других языков
		break;
	case 'Gname':
		$metadata['humanName'] = json_decode($data['value'],true);
		break;
	case 'format':
		$metadata['ext'] = $data['value'];
		break;
	case 'bounds':
		if(!$metadata['bounds']){
			$bounds = explode(',',$data['value']);
			array_walk($bounds,function (&$val){$val=(int)$val;});
			if(($bounds[0]==$bounds[2]) or ($bounds[1]==$bounds[3])) break;	// находятся кретины...
			$metadata['bounds'] = array('leftTop'=>array('lat'=>$bounds[3],'lng'=>$bounds[0]),'rightBottom'=>array('lat'=>$bounds[1],'lng'=>$bounds[2]));
		};
		break;
	case 'Gbounds':
		$metadata['bounds'] = json_decode($data['value'],true);
		break;
	case 'minzoom':
		$metadata['minZoom'] = (int)$data['value'];
		break;
	case 'maxzoom':
		$metadata['maxZoom'] = (int)$data['value'];
		break;
	case 'G':
		$getTileQuery = "SELECT tile_data, tile_timestamp FROM Gtiles WHERE ";
		$isGaladrielCacheScheme = true;
		break;
	};
};
echo "metadata:"; print_r($metadata); echo "\n";

$umask = umask(0); 	// сменим на 0777 и запомним текущую
@mkdir(__DIR__."/sockets", 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
$sockName = __DIR__."/sockets/tileproxy_$r";	// Просто в /tmp/ класть нельзя, ибо оно индивидуально для каждого процесса из-за systemd PrivateTmp
@unlink($sockName);
//exec("rm -f $sockName");	// на всякий случай
$masterSock = socket_create(AF_UNIX, SOCK_STREAM, 0);
socket_set_option($masterSock, SOL_SOCKET, SO_REUSEADDR, 1);	// чтобы можно было освободить ранее занятый адрес, не дожидаясь, пока его освободит система
$res = socket_bind($masterSock, $sockName);
chmod($sockName,0666); 	// чтобы при запуске от другого юзера была возможность 
umask($umask); 	// 	Вернём.
//echo "Пытаемся открыть сокет:".socket_strerror(socket_last_error($masterSock))."\n";
if($err = socket_last_error($masterSock)) { 	// с сокетом проблемы
	switch($err){
	case 98:	// Address already in use
		for($i=0;$i<4;$i++){
			//echo "Пытаемся закрыть старый и создать новый сокет\n";
			socket_close($masterSock);
			unlink($sockName);
			$masterSock = socket_create(AF_UNIX, SOCK_STREAM, 0);
			socket_set_option($masterSock, SOL_SOCKET, SO_REUSEADDR, 1);	// чтобы можно было освободить ранее занятый адрес, не дожидаясь, пока его освободит система
			$res = socket_bind($masterSock, $sockName);
			if($res) break;
			usleep(500000);
		};
		if(!$res){
			//echo "Failed to bind to master socket by: " . socket_strerror(socket_last_error($masterSock)) . "                                 \n"; 	// в общем-то -- обычное дело. Клиент закрывает соединение, мы об этом узнаём при попытке чтения.
			error_log("Failed to bind to master socket by: " . socket_strerror(socket_last_error($masterSock)));
			exit(1);
		};
		break;
	default:
		//echo "Error with master socket, exit by: " . socket_strerror(socket_last_error($masterSock)) . "                                 \n";
		error_log("Error with master socket, exit by: " . socket_strerror(socket_last_error($masterSock)));
		exit(1);
	}
}
socket_listen($masterSock,100);
echo "Unix socket $sockName ready to connection\n";

$sockets = array(); 	// список функционирующих сокетов
$outMessages = array();	// сообщения для отправки, массив массивов сообщений, число элементов массива равно числу элементов в $sockets, и соответствующий элемент -- это то, что нужно отправить в соответствующий сокет
$inMessages = array();	// сообщения, получаемые от клиентов, число элементов массива равно числу элементов в $sockets, и соответствующий элемент -- это то, что получено от соответствующего сокета
$socksRead = array(); $socksWrite = array(); $socksError = array(); 	// массивы для изменивших состояние сокетов (с учётом, что они в socket_select() по ссылке, и NULL прямо указать нельзя)
$write = false;
do {
	$socksRead = $sockets; 	// мы собираемся читать все сокеты
	$socksRead[] = $masterSock; 	// 
	$socksError = $sockets; 	// 
	$socksError[] = $masterSock; 	// 
	// сокет всегда готов для чтения, есть там что-нибудь или нет, поэтому если в socksWrite что-то есть, socket_select никогда не ждёт, возвращая socksWrite неизменённым
	$socksWrite = array(); 	// очистим массив 
	foreach($outMessages as $n => $data){ 	// пишем только в сокеты, полученные от masterSock путём socket_accept
		//echo "Заполнение socksWrite: для сокета №$n есть данные ";print_r($data);echo"\n";
		if($data)	$socksWrite[] = $sockets[$n]; 	// если есть, что писать -- добавим этот сокет в те, в которые будем писать
	};
	echo "Has ".(count($sockets))." client socks, and master socks. Ready ".count($socksRead)." read and ".count($socksWrite)." write socks\r";

	$num_changed_sockets = socket_select($socksRead, $socksWrite, $socksError, $SocketTimeout); 	// должно ждать

	// теперь в $socksRead только те сокеты, куда пришли данные, в $socksWrite -- те, откуда НЕ считали, т.е., не было, что читать, но они готовы для чтения
	if (($num_changed_sockets === FALSE) or $socksError) { 	// Warning не перехватываются, включая supplied resource is not a valid Socket resource И смысл?
		echo "socket_select: Error on sockets: " . socket_strerror(socket_last_error()) . "\n";
		foreach($socksError as $socket){
			unset($socket);
		};
	}
	elseif($num_changed_sockets === 0) {	// оборот по таймауту
		echo "\nLoop by timeout. SocketTimeout=$SocketTimeout;\n";
		break;
	};

	//echo "\n Пишем в сокеты ".count($socksWrite)."\n"; //////////////////
	// Здесь пишется в сокеты то, что попало в $outMessages на предыдущем обороте.
	// Тогда соответствующие сокеты проверены на готовность, и готовые попали в $socksWrite. 
	foreach($socksWrite as $socket){
		$n = array_search($socket,$sockets);	// 
		$msg = serialize($outMessages[$n])."\n";	// \n необходим, если на той стороне принимают в PHP_NORMAL_READ, и не обязателен, если в PHP_BINARY_READ
		//echo "\nto $n:\n|$msg|\n";
		$msgLen = mb_strlen($msg,'8bit');
		//echo "Посылаем клиенту №$n $msgLen байт           \n";
		do{	// Пишем большие объёмы в местном цикле, чтобы быстрее.
			// Разницы не заметно, хотя, вроде, MSG_EOR гарантирует посылку всего одним сообщением.
			// Однако, оно так и так посылается одним сообщением
			$res = socket_write($socket, $msg, $msgLen);	
			//$res = socket_send($socket, $msg, $msgLen,MSG_EOR);
			//echo "Послали $res байт\n";
			if($res === FALSE) { 	// клиент умер
				echo "\nFailed to write data to socket $n by: " . socket_strerror(@socket_last_error($sock)) . "\n";	// $sock уже может не быть сокетом
				closeSock($n);
				continue 2;	// к следующему сокету
			}
			elseif($res < $msgLen){	// клиент не принял всё. У него проблемы?
				echo "\nNot all data was writed to socket $n by: " . socket_strerror(socket_last_error($sock)) . "\n";
				$msgLen-=$res;
				continue;	//
			};
			// По причине нехватки буферов или неудачного положения светил может быть Note https://www.php.net/manual/en/function.socket-write.php
			// т.е., данные надо отправлять в цикле.
			// Но данные у нас двоичные, и нет сигнала об окончании данных.
			// Поэтому сигналом об окончании данных будет закрытие сокета.
			// Однако, использование MSG_EOR как бы обещает отправку всего одним сообщением с информированием
			// об окончании сообщения. Но это, видимо, для тех, кто понимает. socket_read не понимает, поэтому
			// надо либо делать socket_set_nonblock($socket); и читать в цикле руками, либо закрывать сокет
			// по окончанию сообщения.
			// На самом деле, сокет всё равно надо закрывать, потому что диалога с клиентом
			// у нас нет.
			closeSock($n);	// закрываем сокет, которому всё отправили. Это сигнал для клиента, что отправили всё.
			break;
		}while(true);
		$outMessages[$n] = '';
		unset($msg);
	}

	//echo "\n Читаем из сокетов ".count($socksRead)."\n"; ///////////////////////
	foreach($socksRead as $socket){
		socket_clear_error($socket);
		if($socket == $masterSock) { 	// новое подключение
			$sock = socket_accept($socket); 	// новый сокет для подключившегося клиента
			//echo "Сокет имеет тип ".gettype($sock)."\n";
			// Это не будет работать в PHP 8, потому что там сокет -- это пустой объект,
			// и он false, и не может быть принят функцией get_resource_type
			//if(!$sock or (get_resource_type($sock) != 'Socket')) {
			if(!(is_object($sock) or $sock or (get_resource_type($sock) == 'Socket'))) {
				echo "Failed to accept incoming by: " . socket_strerror(socket_last_error($socket)) . "\n";
				chkSocks($sock);
				continue;	// к следующему сокету
			};
			$sockets[] = $sock; 	// добавим новое входное подключение к имеющимся соединениям
			//echo "New client connected                                                      \n";
		    continue; 	//  к следующему сокету
		};
		// Читаем клиентские сокеты
		$n = array_search($socket,$sockets);	// 
		$bufSize = 1048576;
		// На самом деле, здесь всё всегда читается за один раз. На самом деле - нет,
		// но собирание частей надо делать через основной цикл, потому что там socket_select
		// которое знает, есть ли ещё данные, или кончились.
		// Но это ничего не меняет - если предполагать, что не все данные считались за один раз,
		// то по-прежнему нет способа узнать, когда они кончились. Т.е., да, за один раз
		// считывается всё, что было в сокете. Но как узнать, было всё, что должно быть, или нет?
		// Поэтому делаем обязательным наличие \n в конце как знака конца данных.
		// ИИ предлагает читать сокет как файл. Но я не понимаю, кто в этои случае пошлёт конец файла.
		//$buf = socket_read($socket, $bufSize, PHP_NORMAL_READ); 	// читаем построчно
		$buf = socket_read($socket, $bufSize, PHP_BINARY_READ);
		//echo "Прочитано из сокета ".mb_strlen($buf,'8bit')." байт\n";
		// В картинке реально встречается \r\n ?, так что PHP_NORMAL_READ невозможно.
		if($err = socket_last_error($socket)) { 	// с сокетом проблемы
			//echo "\nbuf has type ".gettype($buf)." and=|$buf|\nwith error ".socket_strerror(socket_last_error($socket))."\n";		
			switch($err){
			case 114:	// Operation already in progress
			case 115:	// Operation now in progress
				break;
			case 104:	// Connection reset by peer		если клиент сразу закроет сокет, в который он что-то записал, то ещё не переданная часть записанного будет отброшена. Поэтому клиент не закрывает сокет вообще, и он закрывается системой с этим сообщением. Но на этой стороне к моменту получения ошибки уже всё считано?
			default:
				echo "Failed to read data from client socket by: " . socket_strerror(socket_last_error($socket)) . "                                 \n"; 	// в общем-то -- обычное дело. Клиент закрывает соединение, мы об этом узнаём при попытке чтения.
				chkSocks($socket);
			};
			continue;	// к следующему сокету
		};
		// Концом данных считается \n, \r или фрагмент данных, приводимых к пустой строке.
		// Последнее потому, что текстов у нас не ожидается.
		if(trim($buf)) {
			$inMessages[$n] .= $buf;
			$enought = substr($buf,-1);
			//echo "Не достаточно?: |$enought|\n";
			if(($enought!="\n") and ($enought!="\r")) continue;	// к следующему сокету
		};
		
		// Собственно, содержательная часть
		//echo "\nПРИНЯТО ОТ КЛИЕНТА ".mb_strlen($buf,'8bit')." байт\n";
		//echo"|$buf|\n";
		extract(unserialize($inMessages[$n]));	// EXTR_OVERWRITE здесь, пожалуй, не нужно.
		$inMessages[$n] = '';
		//echo "\nПРИНЯТО ОТ КЛИЕНТА и декодировано: request=$request; z=$z; x=$x; y=$y; img: ".(mb_strlen($img,'8bit'))." байт\n";
		//error_log("Reciewed and decoded: request=$request; z=$z; x=$x; y=$y;");
		$n = array_search($socket,$sockets);	// 
		switch($request){
		case 'getTile':
			// Переход от номеров XYZ к номерам mbtiles:
			// zoom_level = z
			// tile_column = x
			// tile_row = 2^z - 1 - $y = (2 << ($z-1)) - 1 -$y
			if($z) $y = (2 << ($z-1)) - 1 -$y;	// не нужно пересчитывать номер нулевого тайла
			//echo $getTileQuery."zoom_level=$z AND tile_column=$x AND tile_row=$y        \n";
			$res = $db->query( $getTileQuery."zoom_level=$z AND tile_column=$x AND tile_row=$y");
			$img = $res->fetchArray(SQLITE3_ASSOC);
			//echo "Запрос: z=$z; x=$x; y=$y;        \n";
			//echo "Ответ:"; print_r($img); echo "\n";
			if($img) $img = $img['tile_data'];	// т.е., тайл есть, хотя, возможно, и null, т.е., тайла не должно быть
			else $img = false;	// тайла ещё нет. Это примерно соответствует соглашению в tiles.php, где false - это 400 Bad Request
			//error_log("Query: z=$z; x=$x; y=$y; Result have img ".(mb_strlen($img,'8bit'))." bytes       ");
			$outMessages[$n] = array('img'=>$img,'ext'=>$metadata['ext'],'timestamp'=>$timestamp);
			unset($img);
			break;
		case 'putTile':
			if(!$isGaladrielCacheScheme){
				$outMessages[$n] = array('success'=>false,'message'=>"The DataBase is not a G database, so putTile is impossible.");
				error_log("The DataBase is not a G database, so putTile is impossible.");
				break;
			};
			//echo "Будем сохранять тайл z=$z; x=$x; y=$y; размером ".(mb_strlen($img,'8bit'))." байт\n";// break;
			$write = true;
			// Переведём из ZXY в TMS
			if($z) $y = (2 << ($z-1)) - 1 -$y;	// не нужно пересчитывать номер нулевого тайла
			$hash = hash('crc32b',$img);
			$timestamp = time();
			// Прикол в том, что BLOB можно INSERT только с помощью подготовленного запроса, потому что
			// в строке VALUES должны быть двоичные данные, а такого библиотека SQLite3 не понимает, и ругается.
			$stmtImages = $db->prepare(
"INSERT OR IGNORE
INTO images ( hash, tile_data, timestamp )
VALUES ( :hash, :tile_data, :timestamp )
"
			);
			try {
				//$res = $db->exec("BEGIN TRANSACTION");	// это не нужно, поскольку любой из обломов не страшен, но могло бы быть полезным для уменьшения числа коммитов
				$stmtImages->bindValue(":hash", $hash, SQLITE3_TEXT);	
				$stmtImages->bindValue(":tile_data", $img, SQLITE3_BLOB);	
				$stmtImages->bindValue(":timestamp", $timestamp, SQLITE3_INTEGER);	
				$stmtImages->execute();	// выполним запрос
				$stmtImages->reset();
				
				$res = $db->exec(
"INSERT OR REPLACE
INTO map ( zoom_level, tile_column, tile_row, tile_id )
VALUES ( $z, $x, $y, '$hash' )
"
				);
				$outMessages[$n] = array('success'=>true,'message'=>'');
			}
			catch(Exception $e){
				error_log("ERROR during insert: ".$e->getMessage());
				//$res = $db->exec("ROLLBACK TRANSACTION");
				$outMessages[$n] = array('success'=>false,'message'=>$e->getMessage());
			};
			break;
		case 'getMetainfo':
		default:
			$outMessages[$n] = $metadata;
		};
	};
} while (true);
foreach($sockets as $socket) {
	socket_close($socket);
};
socket_close($masterSock);
unlink($sockName);

if($isGaladrielCacheScheme){
	$res = $db->query("SELECT * FROM tiles LIMIT 1");	// не пуста ли база данных?
	if(!$res->fetchArray(SQLITE3_ASSOC)){	// база данных пуста
		$db->close();
		unlink($DBfile);
	}
	else {
		if($write) {
			require_once 'mapsourcesVariablesList.php';	// умолчальные переменные и функции описания карты, полный комплект
			@include_once "$mapSourcesDir/$r.php";	// собственно, оно не надо. А здесь - чтобы сохранить соглашение о глобальности.
			updMetadata($db);	// мы писали в эту базу - обновим таблицу metadata, вдруг они изменились
		};
		$db->close();
	};
};



function chkSocks($socket) {
/**/
global $sockets, $socksRead, $socksWrite, $socksError, $outMessages;
$n = array_search($socket,$sockets);	// 
//echo "Check client socket #$n $socket type ".gettype($socket)." by error or by life     \n";
if($n !== FALSE){
	unset($sockets[$n]);
	unset($outMessages[$n]);
	unset($inMessages[$n]);
}
$n = array_search($socket,$socksRead);	// 
if($n !== FALSE) unset($socksRead[$n]);
$n = array_search($socket,$socksWrite);	// 
if($n !== FALSE) unset($socksWrite[$n]);
$n = array_search($socket,$socksError);	// 
if($n !== FALSE) unset($socksError[$n]);
@socket_close($socket); 	// он может быть уже закрыт
//echo "\nchkSocks sockets: "; print_r($sockets);
}; // end function chkSocks


function closeSock($n) {
/**/
global $sockets, $socksRead, $socksWrite, $socksError, $outMessages;
//echo "Close client socket #$n                    \n";
$i = array_search($sockets[$n],$socksRead);	// 
if($i !== FALSE) unset($socksRead[$i]);
$i = array_search($sockets[$n],$socksWrite);	// 
if($i !== FALSE) unset($socksWrite[$i]);
$i = array_search($sockets[$n],$socksError);	// 
if($i !== FALSE) unset($socksError[$i]);
@socket_close($sockets[$n]); 	// он может быть уже закрыт
unset($sockets[$n]);
unset($outMessages[$n]);
unset($inMessages[$n]);
}; // end function closeSock


function createDB($DBname){
/**/
global $tileCacheDir;
$DBfile = "$tileCacheDir/$DBname.mbtiles";
try{
	$db = new SQLite3($DBfile,SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
}
catch(Exception $e){
	error_log("ERROR with create $DBfile: ".$e->getMessage());
	$db->close();
	@unlink($DBfile);
	return false;
};
chmod($DBfile,0666); 	// чтобы при запуске от другого юзера была возможность 

$res = $db->exec(
"CREATE TABLE metadata (
    name TEXT,
    value TEXT,
    PRIMARY KEY ( name )
)
"
);
if(!$res) {
	//echo "Не создалась таблица metadata\n";
	$db->close();
	@unlink($DBfile);
	return false;
};

$res = $db->exec(
"CREATE TABLE images (
    hash TEXT,
    tile_data BLOB,
    timestamp INTEGER,
    PRIMARY KEY ( hash )
)
"
);
if(!$res) {
	//echo "Не создалась таблица images\n";
	$db->close();
	@unlink($DBfile);
	return false;
};

$res = $db->exec(
"CREATE TABLE map (
   zoom_level INTEGER,
   tile_column INTEGER,
   tile_row INTEGER,
   tile_id TEXT NOT NULL,
   PRIMARY KEY ( zoom_level, tile_column, tile_row ),
   FOREIGN KEY ( tile_id ) REFERENCES images ( hash ) ON DELETE CASCADE ON UPDATE CASCADE
)
"
);
if(!$res) {
	//echo "Не создалась таблица map\n";
	$db->close();
	@unlink($DBfile);
	return false;
};

$res = $db->exec(
"CREATE VIEW tiles AS
	SELECT
		map.zoom_level AS zoom_level,
		map.tile_column AS tile_column,
		map.tile_row AS tile_row,
		images.tile_data AS tile_data
	FROM map, images
	WHERE images.hash = map.tile_id
"
);
if(!$res) {
	//echo "Не создалась VIEW tiles\n";
	$db->close();
	@unlink($DBfile);
	return false;
};
$res = $db->exec(
"CREATE VIEW Gtiles AS
	SELECT
		map.zoom_level AS zoom_level,
		map.tile_column AS tile_column,
		map.tile_row AS tile_row,
		images.tile_data AS tile_data,
		images.timestamp AS tile_timestamp
	FROM map, images
	WHERE images.hash = map.tile_id
"
);
if(!$res) {
	//echo "Не создалась VIEW Gtiles\n";
	$db->close();
	@unlink($DBfile);
	return false;
};
if(!updMetadata($db,true)) {	// заполним metadata
	//echo "Не заполнилась metadata \n";
	$db->close();
	@unlink($DBfile);
	return false;
};

$db->close();
return true;
}; // end function createDB


function updMetadata($db,$new=false){
global $humanName,$ext,$minZoom,$maxZoom,$bounds;
$insertStr = 'INSERT OR REPLACE INTO metadata ( name, value ) VALUES ';
if($new) $insertStr .= " ( 'G', 1 ),";
if($humanName){
	$insertStr .= " ( 'name', '{$humanName['en']}' ),";
	$insertStr .= " ( 'Gname', '".json_encode($humanName,JSON_UNESCAPED_UNICODE)."' ),";
};
if($ext) $insertStr .= " ( 'format', '$ext' ),";
if($minZoom) $insertStr .= " ( 'minzoom', $minZoom ),";
if($maxZoom) $insertStr .= " ( 'maxzoom', $maxZoom ),";
if($bounds){
	$insertStr .= " ( 'bounds', '{$bounds['leftTop']['lng']},{$bounds['rightBottom']['lat']},{$bounds['rightBottom']['lng']},{$bounds['leftTop']['lat']}' ),";
	$insertStr .= " ( 'Gbounds', '".json_encode($bounds,JSON_UNESCAPED_UNICODE)."' ),";
};
$insertStr = substr($insertStr,0,-1);	// отрезаем последнюю запятую
if(substr($insertStr,-1)!='S'){	// есть какие-то данные для добавления
	$res = $db->exec($insertStr);
	if(!$res) return false;
};
return true;
}; // end function updMetadata

?>
