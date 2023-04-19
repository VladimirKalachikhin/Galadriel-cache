<?php
chdir(__DIR__); // задаем директорию выполнение скрипта
require('./params.php'); 	// пути и параметры (без указания пути оно сперва ищет в include_path, а он не обязан начинаться с .)

$SocketTimeout = 10;	// демон умрёт через сек.

$r = filter_var($argv[1],FILTER_SANITIZE_FULL_SPECIAL_CHARS);	// один параметр -- имя карты без расширения
if(!$r) return;
if(!file_exists("$tileCacheDir/$r.mbtiles")) return;	// Придурок, который писал класс SQLite3, не знал, что файла может не быть.

$db = new SQLite3("$tileCacheDir/$r.mbtiles",SQLITE3_OPEN_READONLY);
$res = $db->query("
SELECT value
FROM metadata
WHERE name='format'
");
$metadata = $res->fetchArray(SQLITE3_ASSOC);
if($metadata) $ext = $metadata['value'];
//if($ext=='pbf') return;	// векторные тайлы не умеем

$sockName = "/tmp/tileproxy_$r";
$masterSock = socket_create(AF_UNIX, SOCK_STREAM, 0);
$res = @socket_bind($masterSock, $sockName);
if($err = socket_last_error($masterSock)) { 	// с сокетом проблемы
	switch($err){
	case 98:	// Address already in use
		$res = @socket_connect($masterSock,$sockName);
		if($res){	// echo "сокет уже обслуживается\n";
			socket_close($masterSock);
			return;
		}
		else {	// echo "сокет не обслуживается\n";
			unlink($sockName);
			$res = socket_bind($masterSock, $sockName);
			if(!$res){
				echo "Failed to bind to master socket by: " . socket_strerror(socket_last_error($masterSock)) . "                                 \n"; 	// в общем-то -- обычное дело. Клиент закрывает соединение, мы об этом узнаём при попытке чтения.
				return;
			}
		}
		break;
	default:
		echo "Failed to bind to master socket by: " . socket_strerror(socket_last_error($masterSock)) . "                                 \n"; 	// в общем-то -- обычное дело. Клиент закрывает соединение, мы об этом узнаём при попытке чтения.
		return;
	}
}
socket_listen($masterSock,100);
$sockets = array(); 	// список функционирующих сокетов
$messages = array();	// сообщения для отправки, массив массивов сообщений, число элементов массива равно числу элементов в $sockets, и соответствующий элемент -- это то, что нужно отправить в соответствующий сокет
$socksRead = array(); $socksWrite = array(); $socksError = array(); 	// массивы для изменивших состояние сокетов (с учётом, что они в socket_select() по ссылке, и NULL прямо указать нельзя)
echo "$sockName ready to connection\n";
do {
	$socksRead = $sockets; 	// мы собираемся читать все сокеты
	$socksRead[] = $masterSock; 	// 
	$socksError = $sockets; 	// 
	$socksError[] = $masterSock; 	// 
	// сокет всегда готов для чтения, есть там что-нибудь или нет, поэтому если в socksWrite что-то есть, socket_select никогда не ждёт, возвращая socksWrite неизменённым
	$socksWrite = array(); 	// очистим массив 
	foreach($messages as $n => $data){ 	// пишем только в сокеты, полученные от masterSock путём socket_accept
		//echo "Заполнение socksWrite: для сокета №$n есть данные ";print_r($data);echo"\n";
		if($data)	$socksWrite[] = $sockets[$n]; 	// если есть, что писать -- добавим этот сокет в те, в которые будем писать
	}
	echo "Has ".(count($sockets))." client socks, and master socks. Ready ".count($socksRead)." read and ".count($socksWrite)." write socks\r";

	$num_changed_sockets = socket_select($socksRead, $socksWrite, $socksError, $SocketTimeout); 	// должно ждать

	// теперь в $socksRead только те сокеты, куда пришли данные, в $socksWrite -- те, откуда НЕ считали, т.е., не было, что читать, но они готовы для чтения
	if (($num_changed_sockets === FALSE) or $socksError) { 	// Warning не перехватываются, включая supplied resource is not a valid Socket resource И смысл?
		echo "socket_select: Error on sockets: " . socket_strerror(socket_last_error()) . "\n";
		foreach($socksError as $socket){
			unset($socket);
		}
	}
	elseif($num_changed_sockets === 0) {	// оборот по таймауту
		echo "\nLoop by timeout. SocketTimeout=$SocketTimeout;\n";
		break;
	}

	echo "\n Пишем в сокеты ".count($socksWrite)."\n"; //////////////////
	// Здесь пишется в сокеты то, что попало в $messages на предыдущем обороте.
	// Тогда соответствующие сокеты проверены на готовность, и готовые попали в $socksWrite. 
	foreach($socksWrite as $socket){
		$n = array_search($socket,$sockets);	// 
		$msg = $messages[$n]."\n\n";
		//echo "\nto $n:\n|$msg|\n";
		$msgLen = mb_strlen($msg,'8bit');
		echo "Посылаем клиенту $msgLen байт\n";
		$res = socket_write($socket, $msg, $msgLen);
		if($res === FALSE) { 	// клиент умер
			echo "\nFailed to write data to socket $n by: " . socket_strerror(@socket_last_error($sock)) . "\n";	// $sock уже может не быть сокетом
			chkSocks($socket);
			continue;	// к следующему сокету
		}
		elseif($res <> $msgLen){	// клиент не принял всё. У него проблемы?
			echo "\nNot all data was writed to socket $n by: " . socket_strerror(socket_last_error($sock)) . "\n";
			chkSocks($socket);
			continue;	// к следующему сокету
		}
		$messages[$n] = '';
		unset($msg);
	}

	echo "\n Читаем из сокетов ".count($socksRead)."\n"; ///////////////////////
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
			}
			$sockets[] = $sock; 	// добавим новое входное подключение к имеющимся соединениям
			echo "New client connected                                                      \n";
		    continue; 	//  к следующему сокету
		}
		// Читаем клиентские сокеты
		$buf = @socket_read($socket, 2048, PHP_NORMAL_READ); 	// читаем построчно
		// строки могут разделяться как \n, так и \r\n, но при PHP_NORMAL_READ reading stops at \n or \r,
		// соотвественно, сперва строка заканчивается на \r, а после следующего чтения - на \r\n,
		// и только тогда можно заменить. В результате строки составного сообщения (заголовки, например)
		// всегда кончаются только \n
		if($err = socket_last_error($socket)) { 	// с сокетом проблемы
			//echo "\nbuf has type ".gettype($buf)." and=|$buf|\nwith error ".socket_last_error($socket)."\n";		
			switch($err){
			case 114:	// Operation already in progress
			case 115:	// Operation now in progress
				break;
			case 104:	// Connection reset by peer		если клиент сразу закроет сокет, в который он что-то записал, то ещё не переданная часть записанного будет отброшена. Поэтому клиент не закрывает сокет вообще, и он закрывается системой с этим сообщением. Но на этой стороне к моменту получения ошибки уже всё считано?
			default:
				echo "Failed to read data from client socket by: " . socket_strerror(socket_last_error($socket)) . "                                 \n"; 	// в общем-то -- обычное дело. Клиент закрывает соединение, мы об этом узнаём при попытке чтения.
				echo "Close client socket type ".gettype($socket)." by error or by life                    \n";
				chkSocks($socket);
			}
		    continue;	// к следующему сокету
		}
		$buf = trim($buf);	// у нас не будет текстов
		if(!$buf) continue;	// к следующему сокету
		// Собственно, содержательная часть
		echo "\nПРИНЯТО ОТ КЛИЕНТА ".mb_strlen($buf,'8bit')." байт\n";
		//echo"|$buf|\n";
		extract(unserialize($buf),EXTR_OVERWRITE);
		// Переход от номеров XYZ к номерам mbtiles:
		// zoom_level = z
		// tile_column = x
		// tile_row = 2^z - 1 - $y = (2 << ($z-1)) - 1 -$y
		if($z) $y = (2 << ($z-1)) - 1 -$y;	// не нужно пересчитывать номер нулевого тайла
		$res = $db->query("
SELECT tile_data
FROM tiles
WHERE zoom_level=$z
	AND tile_column=$x
	AND tile_row=$y
");
		$img = $res->fetchArray(SQLITE3_ASSOC);
		if($img) $img = $img['tile_data'];
		// array('img'=>$img,'ContentType'=>$ContentType,'content_encoding'=>$content_encoding,'ext'=>$ext);
		$n = array_search($socket,$sockets);	// 
		$messages[$n] = serialize(array('img'=>$img,'ext'=>$ext));
		unset($img);
	}
} while (true);
foreach($sockets as $socket) {
	socket_close($socket);
}
socket_close($masterSock);
unlink($sockName);

function chkSocks($socket) {
/**/
global $sockets, $socksRead, $socksWrite, $socksError, $messages;
$n = array_search($socket,$sockets);	// 
//echo "Close client socket #$n $socket type ".gettype($socket)." by error or by life                    \n";
if($n !== FALSE){
	unset($sockets[$n]);
	unset($messages[$n]);
}
$n = array_search($socket,$socksRead);	// 
if($n !== FALSE) unset($socksRead[$n]);
$n = array_search($socket,$socksWrite);	// 
if($n !== FALSE) unset($socksWrite[$n]);
$n = array_search($socket,$socksError);	// 
if($n !== FALSE) unset($socksError[$n]);
@socket_close($socket); 	// он может быть уже закрыт
//echo "\nchkSocks sockets: "; print_r($sockets);
} // end function chkSocks




?>
