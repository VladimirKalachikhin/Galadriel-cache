<?php
function serveMBTiles($r,$z,$x,$y){
/* Получает (растровый) тайл из файла $r.mbtiles, от демона, который этот файл пасёт.
При отсутствии демона -- запускает его.
Предполагается, что переменные из params.php глобальны 
*/
global $phpCLIexec;

$sockName = "/tmp/tileproxy_$r";
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
$res = @socket_connect($socket,$sockName);
if(!$res){	// нет живого сокета
	exec("$phpCLIexec MBTilesServer.php $r > /dev/null 2>&1 &"); 	// запустим срвер exec не будет ждать завершения
	sleep(1);
	$res = @socket_connect($socket,$sockName);
}
if(!$res) return array('img'=>null);

$msg = serialize(array('z'=>$z,'x'=>$x,'y'=>$y))."\n";
$msgLen = mb_strlen($msg,'8bit');
echo "Посылаем серверу $msgLen байт:|$msg|\n";
$res = socket_write($socket, $msg, $msgLen);	// Посылаем запрос
if(!$res) return array('img'=>null);
$buf = socket_read($socket, 102400,   PHP_BINARY_READ);	// Считаем, что обмениваемся только одним сообщением
//$buf = '';
//while($buf .= socket_read($socket, 102400,   PHP_BINARY_READ)){	// это не работает как написано: после чтения отсутствующих данных ничего не происходит: ни возврата false, ни ошибки. Также при чтении из закрытого сокета false возвращается, но к этому моменту ещё не всё присланное прочитано, и остаток обрезается.
//	if(substr($buf,-2)==="\n\n") break;
//}
$buf = trim($buf);
if(!$buf) return array('img'=>null);
$buf = unserialize($buf);
if(!$buf) return array('img'=>null);
return $buf;
} // end function serveMBTiles
?>
