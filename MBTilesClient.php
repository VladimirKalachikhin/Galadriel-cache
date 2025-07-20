<?php
function serveMBTiles($r,$z,$x,$y){
/* Получает (растровый) тайл из файла $r.mbtiles, от демона, который этот файл пасёт.
При отсутствии демона -- запускает его.
Предполагается, что переменные из params.php глобальны 
*/
global $phpCLIexec;

$sockName = __DIR__."/tmp/tileproxy_$r";
//echo "[serveMBTiles] r=$r, z=$z, x=$x, y=$y, sockName=$sockName;\n";
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
$res = socket_connect($socket,$sockName);
if(!$res){	// нет живого сокета
	exec("$phpCLIexec MBTilesServer.php $r > /dev/null 2>&1 &"); 	// запустим срвер exec не будет ждать завершения
	sleep(1);
	$res = @socket_connect($socket,$sockName);
}
if(!$res) return array('img'=>null);

$msg = serialize(array('z'=>$z,'x'=>$x,'y'=>$y))."\n";
$msgLen = mb_strlen($msg,'8bit');
//echo "[serveMBTiles] Посылаем серверу $msgLen байт:|$msg|\n";
$res = socket_write($socket, $msg, $msgLen);	// Посылаем запрос, считаем, что он короткий и гарантированно отдастся за один socket_write
if(!$res) return array('img'=>null);
// По причине нехватки буферов и неудачного расположения светил данные могут поступать в сокет
// неполностью. Поэтому нужно читать, пока не кончится.
// Однако, поскольку мы читаем в режиме PHP_BINARY_READ, мы не знаем, когда данные кончились:
// даже если прочли уже всё, никакого сигнала об окончании данных не поступает.
// Даже если указать большой буфер, и считать, что если приняли меньше буфера - то приняли всё,
// данные могут поступать маленькими кусками, меньше буфера.
// Поэтому сигналом о том, что данные кончились является закрытие сокета сервером.
$result = ''; $bufSize = 1048576;
do{
	//error_log("[serveMBTiles] Wait from server");
	$buf = socket_read($socket, $bufSize, PHP_BINARY_READ);
	//echo "[serveMBTiles] Получили от сервера: ".(mb_strlen($buf,'8bit'))." байт\n";// "|$result|\n";
	//error_log("[serveMBTiles] Recieved from server: ".(mb_strlen($buf,'8bit'))." bytes");
	if($buf!==false){
		$result .= $buf;
		//if(mb_strlen($buf,'8bit')<=$bufSize) break;	// прочли всё - не обязательно, данные могли идти маленькими кусками
	};
	// else - на той стороне закрыли сокет, по облому или передали всё
}while($buf);
@socket_close($socket); 	// он может быть уже закрыт

$result = unserialize($result);
//error_log("[serveMBTiles] Request: r=$r, z=$z, x=$x, y=$y; Respoce: ".(mb_strlen($result['img'],'8bit'))." bytes of {$result['ext']}");
//echo "[serveMBTiles] Декодировали: ".(mb_strlen($result['img'],'8bit'))." байт\n";// "|$result|\n";
if(!$result) return array('img'=>null);
return $result;
} // end function serveMBTiles
?>
